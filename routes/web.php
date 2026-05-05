<?php

use App\Application;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemRestController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

if (config('app.url') !== 'http://localhost') {
    URL::forceRootUrl(config('app.url'));
}

Route::get('/userselect/{user}', [LoginController::class, 'setUser'])->name('user.set');
Route::get('/userselect', [UserController::class, 'selectUser'])->name('user.select');
Route::get('/autologin/{uuid}', [LoginController::class, 'autologin'])->name('user.autologin');

Route::get('/', [ItemController::class,'dash'])->name('dash');
Route::get('check_app_list', [ItemController::class,'checkAppList'])->name('applist');

Route::get('single/{appid}', function ($appid) {
    return json_encode(Application::single($appid));
})->name('single');

/**
 * Tag Routes
 */
Route::resource('tags', TagController::class);

Route::name('tags.')->prefix('tag')->group(function () {
    Route::get('/{slug}', [TagController::class, 'show'])->name('show');
    Route::get('/add/{tag}/{item}', [TagController::class, 'add'])->name('add');
    Route::get('/restore/{id}', [TagController::class, 'restore'])->name('restore');
});


/**
 * Item Routes
 */
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/items/websitelookup/{url}', [ItemController::class, 'websitelookup'])->name('lookup');
});

Route::resource('items', ItemController::class);

Route::name('items.')->prefix('items')->group(function () {
    Route::get('/pin/{id}', [ItemController::class, 'pin'])->name('pin');
    Route::get('/restore/{id}', [ItemController::class, 'restore'])->name('restore');
    Route::get('/unpin/{id}', [ItemController::class, 'unpin'])->name('unpin');
    Route::get('/pintoggle/{id}/{ajax?}/{tag?}', [ItemController::class, 'pinToggle'])->name('pintoggle');
});

Route::post('order', [ItemController::class,'setOrder'])->name('items.order');
Route::post('appload', [ItemController::class,'appload'])->name('appload');
Route::post('test_config', [ItemController::class,'testConfig'])->name('test_config');
Route::get('get_stats/{id}', [ItemController::class,'getStats'])->name('get_stats');

Route::get('/search', [SearchController::class,'index'])->name('search');
Route::get('/search/autocomplete', [SearchController::class,'autocomplete'])->name('search.autocomplete');

Route::get('view/{name_view}', function ($name_view) {
    return view('SupportedApps::'.$name_view)->render();
});

Route::get('titlecolour', function (Request $request) {
    $color = $request->input('color');
    if ($color) {
        return title_color($color);
    }
    return '';
})->name('titlecolour');

Route::resource('users', UserController::class);

/**
 * Settings.
 */
Route::name('settings.')->prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class,'index'])->name('index');
    Route::get('edit/{id}', [SettingsController::class,'edit'])->name('edit');
    Route::get('clear/{id}', [SettingsController::class,'clear'])->name('clear');
    Route::patch('edit/{id}', [SettingsController::class,'update']);
});

Auth::routes(['register' => false]);

Route::get('/home', [HomeController::class,'index'])->name('home');

Route::resource('api/item', ItemRestController::class);
Route::get('import', ImportController::class)->name('items.import');

Route::get('/health', HealthController::class)->name('health');

// JSON-Infos zum Bild (heute oder älter via ?idx=0..7)
Route::get('/bing-info', function (\Illuminate\Http\Request $request) {
    $idx = max(0, min(7, (int) $request->query('idx', 0)));
    $json = getBingJson($idx);

    if (!$json || empty($json['images'][0])) {
        return response()->json(['error' => 'no data'], 404);
    }

    $img = $json['images'][0];

    return response()->json([
        'idx'           => $idx,
        'maxIdx'        => 7,
        'title'         => $img['title']          ?? '',
        'copyright'     => $img['copyright']      ?? '',
        'copyrightlink' => isset($img['copyrightlink'])
            ? (str_starts_with($img['copyrightlink'], 'http')
                ? $img['copyrightlink']
                : 'https://www.bing.com' . $img['copyrightlink'])
            : null,
        'fullstartdate' => $img['fullstartdate']  ?? '',
        'startdate'     => $img['startdate']      ?? '',
        'imageUrl' => 'https://www.bing.com' . $img['url'],
        //'imageUrl'      => url('/bing-bg.jpg?idx=' . $idx . '&v=' . ($img['startdate'] ?? '')),
    ])->header('Cache-Control', 'public, max-age=600');
});

if (!function_exists('getBingJson')) {
    function getBingJson(int $idx): ?array {
        $cacheKey = 'bing_bg_json_' . $idx;
        $json = Cache::get($cacheKey);

        if (!$json) {

            // expiresAt immer vom Index 0 ableiten
            $expiresAt = Cache::get('bing_bg_expires_at');

            if (!$expiresAt) {
                // https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1&mkt=de-CH
                $json0 = Http::get('https://www.bing.com/HPImageArchive.aspx', [
                    'format' => 'js', 'idx' => 0, 'n' => 1, 'mkt' => 'de-CH',
                ])->json();

                if (empty($json0['images'][0])) {
                    return null;
                }

                $image0    = $json0['images'][0];
                $time      = substr($image0['fullstartdate'], 8, 4);
                $expiresAt = Carbon::createFromFormat('YmdHi', $image0['enddate'] . $time, 'UTC');

                Cache::put('bing_bg_expires_at', $expiresAt, $expiresAt);
                Cache::put('bing_bg_json_0', $json0, $expiresAt);

                if ($idx === 0) {
                    return $json0;
                }
            }

            $json = Http::get('https://www.bing.com/HPImageArchive.aspx', [
                'format' => 'js', 'idx' => $idx, 'n' => 1, 'mkt' => 'de-CH',
            ])->json();

            if (empty($json['images'][0])) {
                return null;
            }

            Cache::put($cacheKey, $json, $expiresAt);
        }

        return $json;
    }
}
