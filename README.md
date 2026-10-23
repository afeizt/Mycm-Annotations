# MycmAnnotations

基于 PHP 8 原生 Attribute 的控制器注解扫描器，兼容旧版 DocBlock 格式。

零外部依赖，可独立作为 Composer 包发布使用。

## 特性

- **PHP 8 原生 Attribute**：使用 `#[Controller]` / `#[Node]` 标记控制器和方法
- **向后兼容**：自动识别旧版 `@ControllerAnnotation` / `@NodeAnotation` DocBlock 格式
- **智能优先级**：同时存在时，PHP 8 Attribute 优先于 DocBlock
- **零外部依赖**：仅需 PHP 8.0 + `ext-tokenizer`（内置扩展）
- **PSR-4 自动加载**：标准 Composer 包，安装即用

## 安装

```bash
composer require mycm/annotations
```

## 快速开始

### 1. 在控制器中标记注解

**新版 PHP 8 Attribute 方式（推荐）：**

```php
<?php
namespace app\admin\controller;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;

#[Controller(title: "管理员管理")]
class Admin
{
    #[Node(title: "列表")]
    public function index() {}

    #[Node(title: "新增")]
    public function add() {}

    #[Node(title: "编辑")]
    public function edit() {}
}
```

**旧版 DocBlock 方式（兼容现有代码，零改动）：**

```php
<?php
namespace app\admin\controller;

/**
 * @ControllerAnnotation(title="管理员管理")
 */
class Admin
{
    /**
     * @NodeAnotation(title="列表")
     */
    public function index() {}

    /**
     * @NodeAnotation(title="新增")
     */
    public function add() {}
}
```

### 2. 扫描控制器

**方式一：单路径扫描（Scanner）**

```php
<?php
use Mycm\Annotations\Scanner;

$scanner = new Scanner();
$controllers = $scanner->scan('/path/to/your/controller/directory');

foreach ($controllers as $ctrl) {
    echo $ctrl->title;          // "管理员管理"
    echo $ctrl->className;      // "app\admin\controller\Admin"
    echo $ctrl->filePath;       // 文件绝对路径

    foreach ($ctrl->nodes as $node) {
        echo $node->title;       // "列表"
        echo $node->methodName;  // "index"
    }
}
```

**方式二：多应用配置扫描（AnnotationManager，推荐）**

支持配置多个应用的控制器路径，一次性扫描所有应用下的控制器和节点，适用于 ThinkPHP 多应用模式（前后台分离管理）。

```php
<?php
use Mycm\Annotations\AnnotationManager;

$manager = new AnnotationManager();

// 链式配置多个应用
$manager
    ->addApp('admin', app_path('admin/controller'))   // 后台
    ->addApp('api', app_path('api/controller'))       // API 接口
    ->addApp('index', app_path('index/controller'));  // 前台

// 或使用 setApps() 批量配置
// $manager->setApps([
//     'admin' => app_path('admin/controller'),
//     'api'   => app_path('api/controller'),
//     'index' => app_path('index/controller'),
// ]);

// 扫描所有应用，按应用分组返回
$results = $manager->scanAll();

foreach ($results as $app) {
    echo $app->appName;              // "admin"
    echo $app->getTotalControllers(); // 该应用的控制器总数
    echo $app->getTotalNodes();       // 该应用的节点总数

    foreach ($app->controllers as $ctrl) {
        echo $ctrl->title;            // "管理员管理"
        foreach ($ctrl->nodes as $node) {
            echo $node->title;         // "列表"
        }
    }
}

// 或者按应用名获取分组节点（便于直接存入数据库）
$grouped = $manager->getAllNodesGroupedByApp();
// 返回：['admin' => [[节点...], ...], 'api' => [[节点...], ...]]

// 扁平化获取所有节点
$allNodes = $manager->getAllNodes();
foreach ($allNodes as $node) {
    // 存入数据库：控制器类名 + 方法名 + 标题 + 所属应用
}
```

## API 参考

### Scanner 类

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `scan(string $directory, array $options = [])` | `ControllerInfo[]` | 扫描指定目录，返回所有控制器信息 |
| `getControllers()` | `ControllerInfo[]` | 获取已扫描的控制器列表 |
| `getNodes()` | `array<string, NodeInfo[]>` | 按控制器类名分组的节点列表 |
| `getAllNodes()` | `NodeInfo[]` | 所有节点的扁平列表 |
| `getController(string $className)` | `ControllerInfo\|null` | 按类名查找单个控制器 |

#### scan() 选项

```php
$controllers = $scanner->scan('/path/to/controllers', [
    'exclude' => ['Abstract*.php', '*Base.php'],  // 排除文件（glob 模式）
]);
```

### AnnotationManager 类（多应用入口）

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `addApp(string $name, string $path)` | `$this` | 链式添加一个应用的控制器路径 |
| `setApps(array $apps)` | `$this` | 批量设置应用配置（`['name' => 'path', ...]`) |
| `removeApp(string $name)` | `$this` | 移除某个应用配置 |
| `getApps()` | `array` | 获取当前已配置的应用列表 |
| `setScanOptions(array $options)` | `$this` | 设置扫描选项（传递到底层 Scanner） |
| `scanAll()` | `AppScanResult[]` | 扫描所有已配置的应用 |
| `scanApp(string $name)` | `AppScanResult\|null` | 扫描单个应用 |
| `getAllControllers()` | `ControllerInfo[]` | 所有应用的控制器扁平列表 |
| `getAllNodes()` | `NodeInfo[]` | 所有应用的节点扁平列表 |
| `getAllNodesGroupedByApp()` | `array` | 按应用名分组的节点信息 |
| `toArray()` | `array` | 所有结果转数组（支持 JSON 序列化） |

### AppScanResult 实体

| 属性 | 类型 | 说明 |
|------|------|------|
| `appName` | `string` | 应用名称，如 "admin"、"api" |
| `controllerPath` | `string` | 控制器目录路径 |
| `controllers` | `ControllerInfo[]` | 该应用下的控制器列表 |

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `getTotalControllers()` | `int` | 该应用下控制器数量 |
| `getTotalNodes()` | `int` | 该应用下节点总数 |
| `toArray()` | `array` | 转为关联数组 |

### ControllerInfo 实体

| 属性 | 类型 | 说明 |
|------|------|------|
| `className` | `string` | 完全限定类名 |
| `title` | `string` | 控制器标题 |
| `filePath` | `string` | 文件绝对路径 |
| `nodes` | `NodeInfo[]` | 该控制器下的节点列表 |

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `toArray()` | `array` | 转为关联数组，便于 JSON 序列化 |

### NodeInfo 实体

| 属性 | 类型 | 说明 |
|------|------|------|
| `methodName` | `string` | 方法名称 |
| `title` | `string` | 节点标题 |
| `controllerClass` | `string` | 所属控制器完全限定类名 |

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `toArray()` | `array` | 转为关联数组，便于 JSON 序列化 |

## 与 ThinkPHP 集成示例

```php
<?php
// 在 ThinkPHP 命令行或定时任务中执行
use Mycm\Annotations\Scanner;

class ScanControllerNode
{
    public function run()
    {
        $scanner = new Scanner();
        $controllers = $scanner->scan(app_path('controller'));

        foreach ($controllers as $ctrl) {
            // 保存控制器信息到数据库
            $ctrlId = Db::name('admin_controller')->insertGetId([
                'class_name' => $ctrl->className,
                'title'      => $ctrl->title,
            ]);

            foreach ($ctrl->nodes as $node) {
                // 保存节点信息到数据库
                Db::name('admin_node')->insert([
                    'controller_id' => $ctrlId,
                    'method_name'   => $node->methodName,
                    'title'         => $node->title,
                ]);
            }
        }
    }
}
```

## 兼容性说明

| 格式 | PHP 版本 | 说明 |
|------|----------|------|
| `#[Controller]` / `#[Node]` | >= 8.0 | PHP 原生 Attribute，推荐使用 |
| `@ControllerAnnotation` / `@NodeAnotation` | 任意 | DocBlock 格式，向后兼容 |

> 如果同时存在两种格式，PHP 8 Attribute 优先级更高。

## 参与开发

```bash
# 克隆仓库
git clone https://github.com/your-org/MycmAnnotations.git

# 运行测试
php tests/run_test.php
```

## License

MIT