<?php

namespace Enna\App\Command;

use Enna\Framework\Console\Command;
use Enna\Framework\Console\Input;
use Enna\Framework\Console\Input\Argument;
use Enna\Framework\Console\Output;

class Build extends Command
{
    /**
     * 应用基础目录
     * @var string
     */
    protected $basePath;

    protected function configure()
    {
        $this->setName('build')
            ->addArgument('app', Argument::OPTIONAL, 'app name')
            ->setDescription('Build App Dirs');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->basePath = $this->app->getBasePath();
        $app = $input->getArgument('app') ?: '';

        if (is_file($this->basePath . 'build.php')) {
            $list = include $this->basePath . 'build.php';
        } else {
            $list = [
                '__dir__' => ['controller', 'model', 'view']
            ];
        }

        $this->buildApp($app, $list);

        $output->writeln("<info>Successed</info>");
    }

    /**
     * Note: 创建应用
     * Date: 2024-03-06
     * Time: 17:47
     * @param string $app 应用名
     * @param array $list 目录结构
     * @return void
     */
    protected function buildApp(string $app, array $list = [])
    {
        if (!is_dir($this->basePath . $app)) {
            mkdir($this->basePath . $app);
        }

        $appPath = $this->basePath . ($app ? $app . DIRECTORY_SEPARATOR : '');
        $namespace = 'app' . ($app ? '\\' . $app : '');

        $this->buildCommon($app);
        $this->buildHello($app, $namespace);

        foreach ($list as $path => $file) {
            if ($path == '__dir__') {
                foreach ($file as $dir) {
                    $this->checkDirBuild($appPath . $dir);
                }
            } elseif ($path == '__file__') {
                foreach ($file as $name) {
                    if (!is_file($appPath . $name)) {
                        file_put_contents($appPath . $name, pathinfo($name, PATHINFO_EXTENSION) == 'php' ? '<?php' . PHP_EOL : '');
                    }
                }
            } else {
                foreach ($file as $val) {
                    $val = trim($val);
                    $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . '.php';
                    $space = $namespace . '\\' . $path;
                    $class = $val;

                    switch ($path) {
                        case 'controller':
                            if ($this->app->config->get('route.controller_suffix')) {
                                $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . 'Controller.php';
                                $class = $val . 'Controller';
                            }
                            $content = "<?php" . PHP_EOL . "namespace {$space};" . PHP_EOL . PHP_EOL . "class {$class}" . PHP_EOL . "{" . PHP_EOL . PHP_EOL . "}";
                            break;
                        case 'model':
                            $content = "<?php" . PHP_EOL . "namespace {$space};" . PHP_EOL . PHP_EOL . "use Enna\Orm\Model;" . PHP_EOL . PHP_EOL . "class {$class} extends Model" . PHP_EOL . "{" . PHP_EOL . PHP_EOL . "}";
                            break;
                        case 'view':
                            $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . '.html';
                            $this->checkDirBuild($filename);
                            $content = '';
                            break;
                        default:
                            $content = "<?php" . PHP_EOL . "namespace {$space};" . PHP_EOL . PHP_EOL . "class {$class}" . PHP_EOL . "{" . PHP_EOL . PHP_EOL . "}";
                    }

                    if (!is_file($filename)) {
                        file_put_contents($filename, $content);
                    }
                }
            }
        }
    }

    /**
     * Note: 创建应用公共文件
     * Date: 2024-03-06
     * Time: 17:55
     * @param string $app 应用名
     * @return void
     */
    protected function buildCommon(string $app)
    {
        $appPath = $this->basePath . ($app ? $app . DIRECTORY_SEPARATOR : '');

        if (!is_file($appPath . 'common.php')) {
            file_put_contents($appPath . 'common.php', "<?php" . PHP_EOL . "// 这是系统自动生成的公共文件" . PHP_EOL);
        }

        foreach (['event', 'middleware', 'config'] as $name) {
            if (!is_file($appPath . $name . '.php')) {
                file_put_contents($appPath . $name . '.php', "<?php" . PHP_EOL . "// 这是系统自动生成的{$name}定义文件" . PHP_EOL . "return [" . PHP_EOL . PHP_EOL . "];" . PHP_EOL);
            }
        }
    }

    /**
     * Note: 创建应用默认请求地址
     * Date: 2024-03-06
     * Time: 18:00
     * @param string $app 应用名称
     * @param string $namespace 命名空间
     * @return void
     */
    protected function buildHello(string $app, string $namespace)
    {
        $suffix = $this->app->config->get('route.controller_suffix') ? 'Controller' : '';
        $filename = $this->basePath . ($app ? $app . DIRECTORY_SEPARATOR : '') . 'controller' . DIRECTORY_SEPARATOR . 'Index' . $suffix . '.php';

        if (!is_file($filename)) {
            $content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Stubs' . DIRECTORY_SEPARATOR . 'controller.stub');

            $content = str_replace(['{%name%}', '{%app%}', '{%layer%}', '{%suffix%}'], [$app, $namespace, 'controller', $suffix], $content);

            $this->checkDirBuild(dirname($filename));

            file_put_contents($filename, $content);
        }
    }

    /**
     * Note: 创建目录
     * Date: 2024-03-06
     * Time: 18:10
     * @param string $dirname 目录
     * @return void
     */
    protected function checkDirBuild(string $dirname)
    {
        if (!is_dir($dirname)) {
            mkdir($dirname);
        }
    }
}