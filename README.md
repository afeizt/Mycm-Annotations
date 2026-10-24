# MycmAnnotations

基于 PHP 8 原生 Attribute 的控制器注解扫描器，零外部依赖。

## 特性

- **纯 PHP 8 Attribute**：使用 `#[Controller]` / `#[Node]` 标记控制器和方法
- **零依赖**：不依赖 `doctrine/annotations` 或任何第三方包
- **高性能**：基于 Reflection API 直接读取 Attribute，无需解析 DocBlock
- **多应用支持**：通过 `AnnotationManager` 同时管理多个应用的控制器扫描
- **节点扁平化**：`getAllNodes()` 将控制器节点（type=1）和方法节点（type=2）合并为统一列表
- **Token 解析**：先从文件 token 提取命名空间/类名，再按需反射，避免不必要的类加载

## 环境要求

- PHP >= 8.0
- ext-tokenizer
- ext-reflection（通常已内置）

## 安装

```bash
composer require mycm/annotations
```

## 快速开始

### 1. 定义控制器

```php
<?php

namespace app\admin\controller;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;

#[Controller(title: '用户管理')]
class User
{
    #[Node(title: '列表')]
    public function index() {}

    #[Node(title: '新增')]
    public function add() {}

    #[Node(title: '删除', is_auth: false)]
    public function delete() {}

    // 无注解的方法不会被识别为节点
    public function _initialize() {}
}
```

### 2. 扫描控制器

```php
use Mycm\Annotations\Scanner;

$scanner = new Scanner();
$controllers = $scanner->scan('/path/to/controller', [
    'namespace_prefix' => 'app\admin\controller',
]);

foreach ($controllers as $ctrl) {
    echo $ctrl->title;           // "用户管理"
    echo $ctrl->className;       // "app\admin\controller\User"
    foreach ($ctrl->nodes as $node) {
        echo $node->title;       // "列表" / "新增" / "删除"
        echo $node->isAuth;      // true / false
        echo $node->getNode();   // "app/admin/controller/User/index"
    }
}
```

### 3. 获取扁平节点列表

```php
$nodes = $scanner->getAllNodes();
// 返回包含控制器节点（type=1）和方法节点（type=2）的统一数组
foreach ($nodes as $node) {
    $arr = $node->toArray();
    // ['node' => '...', 'title' => '...', 'is_auth' => true, 'type' => 2, ...]
}
```

### 4. 多应用模式（AnnotationManager）

```php
use Mycm\Annotations\AnnotationManager;

$manager = new AnnotationManager();
$manager->addApp('admin', '/path/to/admin/controller');
$manager->addApp('api', '/path/to/api/controller');

// 扫描所有应用
$results = $manager->scanAll();

// 按应用分组获取节点
$nodesByApp = $manager->getAllNodesGroupedByApp();
// ['admin' => [[...], [...]], 'api' => [[...], [...]]]

// 获取所有控制器扁平列表
$allControllers = $manager->getAllControllers();
```

### 5. 单应用便捷模式

```php
$manager = new AnnotationManager('/path/to/controller');
$controllers = $manager->scan();
$nodes = $manager->getNodes();
$ctrl = $manager->getController('app\admin\controller\User');
```

## API 参考

### Scanner

| 方法 | 说明 |
|------|------|
| `scan(string $directory, array $options = [])` | 扫描目录，返回 `ControllerInfo[]` |
| `getControllers()` | 获取已扫描的控制器列表 |
| `getNodes()` | 按类名分组的节点 `array<string, NodeInfo[]>` |
| `getAllNodes()` | 扁平节点列表（含控制器节点 + 方法节点） |
| `getController(string $className)` | 按类名查找单个控制器 |

**Scan 选项：**

| 选项 | 类型 | 说明 | 默认值 |
|------|------|------|--------|
| `namespace_prefix` | string | 命名空间前缀（用于生成旧格式 node 路径） | `''` |
| `controller_attribute_class` | string | 控制器级 Attribute 类名 | `Mycm\Annotations\Attributes\Controller` |
| `node_attribute_class` | string | 方法级 Attribute 类名 | `Mycm\Annotations\Attributes\Node` |
| `auth_field_name` | string | Attribute 中鉴权字段名 | `'is_auth'` |
| `exclude` | string[] | 排除的文件名 glob 模式 | `[]` |

### AnnotationManager

| 方法 | 说明 |
|------|------|
| `scan(?string $path = null)` | 扫描单应用，返回 `ControllerInfo[]` |
| `getControllers()` | 获取缓存的控制列表 |
| `getNodes()` | 获取扁平节点列表 |
| `getController(string $className)` | 按类名查找控制器 |
| `setAppPath(string $path)` | 设置/切换应用路径 |
| `addApp(string $name, string $path)` | 添加多应用配置 |
| `setApps(array $apps)` | 批量设置应用配置 |
| `scanAll()` | 扫描所有应用，返回 `AppScanResult[]` |
| `scanApp(string $name)` | 扫描单个已配置的应用 |
| `getAllControllers()` | 所有应用的控制器扁平列表 |
| `getAllNodes()` | 所有应用的节点扁平列表 |
| `getAllNodesGroupedByApp()` | 按应用分组返回节点 |
| `toArray()` | 转为数组格式 |
| `setScanOptions(array $options)` | 设置传递给 Scanner 的选项 |

### Attributes

#### Controller

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    public function __construct(
        public string $title = '',   // 控制器标题
    ) {}
}
```

#### Node

```php
#[Attribute(Attribute::TARGET_METHOD)]
class Node
{
    public function __construct(
        public string $title = '',     // 节点标题
        public bool   $is_auth = true, // 是否需要权限验证
    ) {}
}
```

### 数据对象

#### ControllerInfo

| 属性 | 类型 | 说明 |
|------|------|------|
| `className` | string | 完全限定类名 |
| `title` | string | 控制器标题 |
| `filePath` | string | 文件路径 |
| `controllerNode` | string | 旧格式节点路径 |
| `nodes` | NodeInfo[] | 节点列表 |

方法：`toArray()`, `formatControllerNode(string $className, string $prefix): string`

#### NodeInfo

| 属性 | 类型 | 说明 |
|------|------|------|
| `methodName` | string | 方法名 |
| `title` | string | 节点标题 |
| `controllerClass` | string | 所属控制器类名 |
| `isAuth` | bool | 是否需要鉴权 |
| `type` | int | 1=控制器节点, 2=方法节点 |
| `controllerNode` | string | 所属控制器的节点路径 |

方法：`getNode(): string`, `toArray(): array`

#### AppScanResult

| 属性 | 类型 | 说明 |
|------|------|------|
| `appName` | string | 应用名称 |
| `controllerPath` | string | 控制器目录路径 |
| `controllers` | ControllerInfo[] | 控制器列表 |

方法：`getTotalNodes(): int`, `getTotalControllers(): int`, `toArray(): array`

## 运行测试

```bash
php tests/run_test.php
```

## 与 EasyAdmin SDK 集成

EasyAdmin SDK 通过自定义 PHP 8 Attribute 类（`EasyAdmin\annotation\ControllerAnnotation` / `NodeAnnotation`）配合本扫描器，实现权限节点的自动采集：

```php
use EasyAdmin\auth\Node;

$node = new Node('/path/to/controller', 'app\admin\controller');
$node->setAuthFieldName('auth');
$list = $node->getNodelist();
// [['node' => '...', 'title' => '...', 'is_auth' => true, 'type' => 2], ...]
```

## 协议

MIT License