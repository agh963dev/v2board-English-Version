<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    private $themes;
    private $path;

    public function __construct()
    {
        $this->path = $path = public_path('theme/');
        $this->themes = array_map(function ($item) use ($path) {
            return str_replace($path, '', $item);
        }, glob($path . '*'));
    }

    private function initTheme($themeName, $configs)
    {
        $data = [];
        foreach ($configs as $config) {
            $data[$config['field_name']] = isset($config['default_value']) ? $config['default_value'] : '';
        }

        $data = var_export($data, 1);
        if (!File::put(base_path() . "/config/theme/{$themeName}.php", "<?php\n return $data ;")) {
            abort(500, "{$themeName}初始化失败");
        }

        try {
            Artisan::call('config:cache');
        } catch (\Exception $e) {
            abort(500, "{$themeName}初始化失败");
        }
    }

    public function getThemes()
    {
        $themeConfigs = [];
        foreach ($this->themes as $theme) {
            $themeConfigFile = $this->path . "{$theme}/config.php";
            if (!File::exists($themeConfigFile)) continue;
            $themeConfig = include($themeConfigFile);
            if (!isset($themeConfig['configs']) || !is_array($themeConfig)) continue;
            $themeConfigs[$theme] = $themeConfig;
            if (config("theme.{$theme}")) continue;
            $this->initTheme($theme, $themeConfig['configs']);
        }
        return response([
            'data' => [
                'themes' => $themeConfigs,
                'active' => config('v2board.frontend_theme', 'v2board')
            ]
        ]);
    }

    public function getThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|in:' . join(',', $this->themes)
        ]);
        return response([
            'data' => config("theme.{$payload['name']}")
        ]);
    }

    public function saveThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|in:' . join(',', $this->themes),
            'config' => 'required|array'
        ]);
        $themeConfigFile = public_path("theme/{$payload['name']}/config.php");
        if (!File::exists($themeConfigFile)) abort(500, '主题不存在');
        $themeConfig = include($themeConfigFile);
        $validateFields = array_column($themeConfig['configs'], 'field_name');
        $config = [];
        foreach ($validateFields as $validateField) {
            $config[$validateField] = isset($payload['config'][$validateField]) ? $payload['config'][$validateField] : '';
        }

        File::ensureDirectoryExists(base_path() . '/config/theme/');

        $data = var_export($config, 1);
        if (!File::put(base_path() . "/config/theme/{$payload['name']}.php", "<?php\n return $data ;")) {
            abort(500, '修改失败');
        }

        try {
            Artisan::call('config:cache');
        } catch (\Exception $e) {
            abort(500, '保存失败');
        }

        return response([
            'data' => $config
        ]);
    }
}
