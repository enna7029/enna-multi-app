<?php
declare(strict_types=1);

namespace Enna\App;

use Enna\Framework\App;
use Closure;
use Enna\Framework\Exception\HttpException;
use Enna\Framework\Request;
use Enna\Framework\Response;

class MultiApp
{
    /**
     * @var App
     */
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Note:
     * Date: 2024-03-06
     * Time: 16:00
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        if (!$this->parseMultiApp()) {
            return $next($request);
        }

        return $this->app->middleware->pipeline('app')
            ->send($request)
            ->then(function ($request) use ($next) {
                return $next($request);
            });
    }

    /**
     * Note: 解析多应用
     * Date: 2024-03-05
     * Time: 10:10
     */
    protected function parseMultiApp()
    {
        $scriptName = $this->getScriptName();
        $defaultApp = $this->app->config->get('app.default_app') ?: 'index';
        $appName = $this->app->http->getName();

        if ($appName || ($scriptName && !in_array($scriptName, ['index', 'router']))) { //手动绑定应用
            $appName = $appName ?: $scriptName;
            $this->app->http->setBind();
        } else {
            //自动识别应用
            $this->app->http->setBind(false);
            $appName = null;

            //域名绑定应用
            $bind = $this->app->config->get('app.domain_bind', []);
            if (!empty($bind)) {
                $subDomain = $this->app->request->subDomain();
                $domain = $this->app->request->domain(true);

                if (isset($bind[$domain])) {
                    $appName = $bind[$domain];
                    $this->app->http->setBind();
                } elseif (isset($bind[$subDomain])) {
                    $appName = $bind[$subDomain];
                    $this->app->http->setBind();
                } elseif (isset($bind['*'])) {
                    $appName = $bind['*'];
                    $this->app->http->setBind();
                }
            }

            //未手动绑定应用
            if (!$this->app->http->isBind()) {
                $path = $this->app->request->pathinfo();
                $map = $this->app->config->get('app.app_map', []);
                $deny = $this->app->config->get('app.deny_app_list', []);

                $name = current(explode('/', $path));
                if (strpos($name, '.')) {
                    $name = strstr($name, '.', true);
                }

                if (isset($map[$name])) {
                    if ($map[$name] instanceof Closure) {
                        $result = call_user_func_array($map[$name], [$this->app]);
                        $appName = $result ?: $name;
                    } else {
                        $appName = $map[$name];
                    }
                } elseif ($name && (array_search($name, $map) !== false || in_array($name, $deny))) {
                    throw new HttpException(404, 'app not exists:' . $name);
                } elseif ($name && isset($map['*'])) {
                    $appName = $map['*'];
                } else {
                    $appName = $name ?: $defaultApp;
                    $appPath = $this->app->http->getPath() ?: $this->app->getBasePath() . $appName . DIRECTORY_SEPARATOR;

                    if (!is_dir($appPath)) {
                        return false;
                    }
                }

                if ($name) {
                    $this->app->request->setRoot('/' . $name);
                    $this->app->request->setPathinfo(strpos($path, '/') ? ltrim(strstr($path, '/'), '/') : '');
                }
            }
        }

        $this->setApp($appName ?: $defaultApp);

        return true;
    }

    /**
     * Note: 获取当前运行入口文件名称
     * Date: 2024-03-05
     * Time: 10:09
     * @return string|string[]
     */
    protected function getScriptName()
    {
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $file = $_SERVER['SCRIPT_FILENAME'];
        } elseif (isset($_SERVER['argv'][0])) {
            $file = realpath($_SERVER['argv'][0]);
        }

        return isset($file) ? pathinfo($file, PATHINFO_FILENAME) : '';
    }

    /**
     * Note: 设置应用
     * Date: 2024-03-05
     * Time: 14:29
     * @param string $appName
     */
    protected function setApp(string $appName)
    {
        $this->app->http->name($appName);

        $appPath = $this->app->http->getPath() ?: $this->app->getBasePath() . $appName . DIRECTORY_SEPARATOR;

        $this->app->setAppPath($appPath);

        $this->app->setNamespace($this->app->config->get('app.app_namespace') ?: 'app\\' . $appName);

        if (is_dir($appPath)) {
            $this->app->setRuntimePath($this->app->getRuntimePath() . $appName . DIRECTORY_SEPARATOR);
            $this->app->http->setRoutePath($this->getRoutePath());

            $this->loadApp($appName, $appPath);
        }
    }

    /**
     * Note: 加载应用文件
     * Date: 2024-03-05
     * Time: 18:45
     * @param string $appName 应用名称
     * @param string $appPath 应用目录
     * @return void
     */
    protected function loadApp(string $appName, string $appPath)
    {
        if (is_file($appPath . 'common.php')) {
            include_once $appPath . 'common.php';
        }

        $files = [];
        $files = array_merge($files, glob($appPath . 'config' . DIRECTORY_SEPARATOR . '*' . $this->app->getConfigExt()));
        foreach ($files as $file) {
            $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }

        if (is_file($appPath . 'event.php')) {
            $this->app->loadEvent(include $appPath . 'event.php');
        }

        if (is_file($appPath . 'middleware.php')) {
            $this->app->middleware->import(include $appPath . 'middleware.php', 'app');
        }

        if (is_file($appPath . 'provider.php')) {
            $this->app->bind(include $appPath . 'provider.php');
        }

        $this->app->loadLangPack($this->app->lang->defaultLang());
    }

    /**
     * Note: 设置路由目录
     * Date: 2024-03-05
     * Time: 18:42
     * @return string
     */
    protected function getRoutePath()
    {
        return $this->app->http->getRoutePath() . 'route' . DIRECTORY_SEPARATOR;
    }
}