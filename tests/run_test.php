<?php

/**
 * 集成测试 - 验证 controller-annotation 库的完整功能
 *
 * 测试内容：
 * 1. PHP 8 原生 Attribute 格式
 * 2. 旧版 DocBlock 兼容格式
 * 3. 混合模式（Attribute 优先）
 * 4. 排除规则
 */

// 手动注册 autoloader（模拟实际项目中的 composer autoload）
spl_autoload_register(function (string $class) {
	$prefix = 'Mycm\\Annotations\\';
	$baseDir = __DIR__ . '/../src/';

	if (str_starts_with($class, $prefix)) {
		$relativeClass = substr($class, strlen($prefix));
		$file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
		if (file_exists($file)) {
			require $file;
		}
	}

	// 测试 Fixtures 的 autoload
	$fixturePrefix = 'Mycm\\Annotations\\Tests\\Fixtures\\';
	if (str_starts_with($class, $fixturePrefix)) {
		$relativeClass = substr($class, strlen($fixturePrefix));
		$file = __DIR__ . '/Fixtures/' . str_replace('\\', '/', $relativeClass) . '.php';
		if (file_exists($file)) {
			require $file;
		}
	}
});

use Mycm\Annotations\Scanner;

$testsDir = __DIR__ . '/Fixtures/Controllers';
$scanner = new Scanner();

echo "========================================\n";
echo "  Controller Annotation Scanner Test\n";
echo "========================================\n\n";

// 测试 1：扫描所有控制器
echo "[Test 1] 扫描所有控制器\n";
echo "----------------------------------------\n";
$controllers = $scanner->scan($testsDir);

printf("扫描到 %d 个控制器\n\n", count($controllers));

foreach ($controllers as $controller) {
	printf("控制器: %s\n", $controller->className);
	printf("  标题: %s\n", $controller->title);
	printf("  文件: %s\n", $controller->filePath);
	printf("  节点数: %d\n", count($controller->nodes));

	foreach ($controller->nodes as $node) {
		printf("    - [%s] %s\n", $node->methodName, $node->title);
	}
	echo "\n";
}

// 测试 2：验证 toArray()
echo "[Test 2] toArray() 输出\n";
echo "----------------------------------------\n";
foreach ($controllers as $controller) {
	$arr = $controller->toArray();
	echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
}

// 测试 3：getAllNodes() 和 getNodes()
echo "[Test 3] getNodes() 和 getAllNodes()\n";
echo "----------------------------------------\n";
$groupedNodes = $scanner->getNodes();
foreach ($groupedNodes as $className => $nodes) {
	printf("%s 下有 %d 个节点\n", $className, count($nodes));
}
printf("所有节点总数: %d\n\n", count($scanner->getAllNodes()));

// 测试 4：按类名查找
echo "[Test 4] getController() 按类名查找\n";
echo "----------------------------------------\n";
$ctrl = $scanner->getController('Mycm\Annotations\Tests\Fixtures\Controllers\UserController');
if ($ctrl) {
	printf("找到控制器: %s (标题: %s)\n", $ctrl->className, $ctrl->title);
} else {
	echo "未找到控制器\n";
}

$ctrl = $scanner->getController('Mycm\Annotations\Tests\Fixtures\Controllers\AdminController');
if ($ctrl) {
	printf("找到控制器: %s (标题: %s)\n", $ctrl->className, $ctrl->title);
} else {
	echo "未找到控制器\n";
}

echo "\n";

// 测试 5：验证不包含魔术方法和非节点方法
echo "[Test 5] 验证不包含魔术方法和非节点方法\n";
echo "----------------------------------------\n";
$userCtrl = $scanner->getController('Mycm\Annotations\Tests\Fixtures\Controllers\UserController');
$methodNames = array_map(fn($n) => $n->methodName, $userCtrl->nodes);
$hasMagic = in_array('_initialize', $methodNames);
printf("_initialize 不应出现在节点中: %s (正确应为 false)\n", $hasMagic ? 'true (错误!)' : 'false');

$adminCtrl = $scanner->getController('Mycm\Annotations\Tests\Fixtures\Controllers\AdminController');
$methodNames = array_map(fn($n) => $n->methodName, $adminCtrl->nodes);
$hasMagic = in_array('_before_index', $methodNames);
printf("_before_index 不应出现在节点中: %s (正确应为 false)\n", $hasMagic ? 'true (错误!)' : 'false');

echo "\n";

// 测试 6：PHP 8 Attribute 优先级验证
echo "[Test 6] Attribute 优先级验证\n";
echo "----------------------------------------\n";
$configCtrl = $scanner->getController('Mycm\Annotations\Tests\Fixtures\Controllers\ConfigController');
foreach ($configCtrl->nodes as $node) {
	if ($node->methodName === 'index') {
		printf("index 方法标题: [%s] (期望: 系统配置, 不应是: 旧版标题-会被忽略)\n", $node->title);
	}
	if ($node->methodName === 'mail') {
		printf("mail 方法标题: [%s] (期望: 邮件设置, 来自 DocBlock 回退)\n", $node->title);
	}
}

// 测试 6b：NodeInfo.isAuth 和 NodeInfo.getNode() 验证
echo "\n[Test 6b] NodeInfo isAuth 与 getNode()\n";
echo "----------------------------------------\n";
$userCtrl = $scanner->getController('Mycm\Annotations\Tests\Fixtures\Controllers\UserController');
foreach ($userCtrl->nodes as $node) {
	if ($node->methodName === 'index') {
		printf("index: getNode() = [%s]\n", $node->getNode());
		printf("index: isAuth = %s (期望: true)\n", $node->isAuth ? 'true' : 'false');
	}
	if ($node->methodName === 'add') {
		printf("add: isAuth = %s (期望: false, Attribute is_auth: false)\n", $node->isAuth ? 'true' : 'false');
	}
}

$adminCtrl2 = $scanner->getController('Mycm\Annotations\Tests\Fixtures\Controllers\AdminController');
foreach ($adminCtrl2->nodes as $node) {
	if ($node->methodName === 'delete') {
		printf("delete: isAuth = %s (期望: false, DocBlock is_auth=0)\n", $node->isAuth ? 'true' : 'false');
	}
	if ($node->methodName === 'index') {
		printf("index: isAuth = %s (期望: true, 默认值)\n", $node->isAuth ? 'true' : 'false');
	}
}

// 测试 NodeInfo.toArray() 包含 node 和 is_auth
echo "\n[Test 6c] NodeInfo.toArray() 兼容格式\n";
echo "----------------------------------------\n";
$firstNode = $userCtrl->nodes[0];
$arr = $firstNode->toArray();
printf("node     = %s\n", $arr['node']);
printf("title    = %s\n", $arr['title']);
printf("is_auth  = %s\n", $arr['is_auth'] ? 'true' : 'false');
printf("包含 'node' 字段: %s\n", isset($arr['node']) ? '✓' : '✗');
printf("包含 'is_auth' 字段: %s\n", isset($arr['is_auth']) ? '✓' : '✗');

// ==============================================================
// AnnotationManager 多应用扫描测试
// ==============================================================
echo "\n[Test 7] AnnotationManager 多应用配置扫描\n";
echo "----------------------------------------\n";

$manager = new \Mycm\Annotations\AnnotationManager();
$manager->addApp('admin', __DIR__ . '/Fixtures/Apps/admin/controller');
$manager->addApp('api', __DIR__ . '/Fixtures/Apps/api/controller');

// 测试 scanAll
$results = $manager->scanAll();
printf("扫描到 %d 个应用\n", count($results));

$passCount = 0;
foreach ($results as $app) {
	printf(
		"  应用 [%s]: %d 个控制器, %d 个节点\n",
		$app->appName,
		$app->getTotalControllers(),
		$app->getTotalNodes()
	);

	// 验证 admin 应用
	if ($app->appName === 'admin') {
		$pass7_1 = $app->getTotalControllers() === 1;   // 1 个控制器
		$pass7_2 = $app->getTotalNodes() === 3;          // 3 个节点
		$pass7_3 = $app->controllers[0]->title === '管理员管理';
		$passCount += ($pass7_1 ? 1 : 0) + ($pass7_2 ? 1 : 0) + ($pass7_3 ? 1 : 0);
		printf("    admin 控制器数: %d (%s)\n", $app->getTotalControllers(), $pass7_1 ? '✓' : '✗');
		printf("    admin 节点数: %d (%s)\n", $app->getTotalNodes(), $pass7_2 ? '✓' : '✗');
		printf("    admin 标题: [%s] (%s)\n", $app->controllers[0]->title, $pass7_3 ? '✓' : '✗');
	}

	// 验证 api 应用
	if ($app->appName === 'api') {
		$pass7_4 = $app->getTotalControllers() === 1;   // 1 个控制器 (DocBlock)
		$pass7_5 = $app->getTotalNodes() === 2;          // 2 个节点
		$pass7_6 = $app->controllers[0]->title === '用户接口';
		$passCount += ($pass7_4 ? 1 : 0) + ($pass7_5 ? 1 : 0) + ($pass7_6 ? 1 : 0);
		printf("    api 控制器数: %d (%s)\n", $app->getTotalControllers(), $pass7_4 ? '✓' : '✗');
		printf("    api 节点数: %d (%s)\n", $app->getTotalNodes(), $pass7_5 ? '✓' : '✗');
		printf("    api 标题: [%s] (%s)\n", $app->controllers[0]->title, $pass7_6 ? '✓' : '✗');
	}
}
printf("子项通过: %d / 6\n", $passCount);

// 测试 getAllControllers (扁平列表)
echo "\n[Test 8] getAllControllers 扁平列表\n";
echo "----------------------------------------\n";
$all = $manager->getAllControllers();
printf("所有应用共有 %d 个控制器\n", count($all));
$pass8 = count($all) === 2; // admin + api
printf("期望 2 个: %s\n", $pass8 ? '✓' : '✗');

// 测试 getAllNodes
echo "\n[Test 9] getAllNodes 扁平节点\n";
echo "----------------------------------------\n";
$allNodes = $manager->getAllNodes();
printf("所有应用共有 %d 个节点\n", count($allNodes));
$pass9 = count($allNodes) === 5; // 3 admin + 2 api
printf("期望 5 个: %s\n", $pass9 ? '✓' : '✗');
// 展示部分节点
foreach (array_slice($allNodes, 0, 3) as $node) {
	printf("  [%s] %s::%s\n", $node->title, basename(str_replace('\\', '/', $node->controllerClass)), $node->methodName);
}

// 测试 getAllNodesGroupedByApp
echo "\n[Test 10] getAllNodesGroupedByApp 按应用分组\n";
echo "----------------------------------------\n";
$grouped = $manager->getAllNodesGroupedByApp();
$pass10a = isset($grouped['admin']) && count($grouped['admin']) === 3;
$pass10b = isset($grouped['api']) && count($grouped['api']) === 2;
printf("admin 分组节点数: %d (%s)\n", count($grouped['admin'] ?? []), $pass10a ? '✓' : '✗');
printf("api 分组节点数: %d (%s)\n", count($grouped['api'] ?? []), $pass10b ? '✓' : '✗');

// 测试 scanApp 单应用扫描
echo "\n[Test 11] scanApp 单应用扫描\n";
echo "----------------------------------------\n";
$adminResult = $manager->scanApp('admin');
$pass11 = $adminResult !== null && $adminResult->appName === 'admin' && $adminResult->getTotalNodes() === 3;
printf("scanApp('admin'): %s\n", $pass11 ? '✓' : '✗');

// 测试未配置的应用
$pass11b = $manager->scanApp('not_exists') === null;
printf("scanApp('not_exists') 返回 null: %s\n", $pass11b ? '✓' : '✗');

// 测试 toArray JSON 序列化
echo "\n[Test 12] toArray JSON 序列化\n";
echo "----------------------------------------\n";
$json = json_encode($manager->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$pass12 = json_last_error() === JSON_ERROR_NONE;
printf("JSON 序列化成功: %s\n", $pass12 ? '✓' : '✗');
if ($pass12) {
	echo substr($json, 0, 500) . "\n...\n";
}

// ==============================================================
// 单应用便捷 API 测试 (construct + setAppPath + scan)
// ==============================================================
echo "\n[Test 13] 构造函数直接传入路径\n";
echo "----------------------------------------\n";
$mgr13 = new \Mycm\Annotations\AnnotationManager(
	__DIR__ . '/Fixtures/Controllers'
);
$controllers13 = $mgr13->scan();
$pass13 = count($controllers13) === 3;
printf("scan() 扫描到 %d 个控制器: %s\n", count($controllers13), $pass13 ? '✓' : '✗');

echo "\n[Test 14] setAppPath() 切换路径 + getControllers()\n";
echo "----------------------------------------\n";
$mgr14 = new \Mycm\Annotations\AnnotationManager();
$mgr14->setAppPath(__DIR__ . '/Fixtures/Controllers');
$controllers14 = $mgr14->getControllers(); // 首次自动触发 scan
$pass14 = count($controllers14) === 3;
printf("getControllers() 返回 %d 个控制器: %s\n", count($controllers14), $pass14 ? '✓' : '✗');

echo "\n[Test 15] getNodes() 返回扁平节点列表\n";
echo "----------------------------------------\n";
$nodes15 = $mgr14->getNodes();
$pass15 = count($nodes15) >= 8;
printf("getNodes() 返回 %d 个节点: %s\n", count($nodes15), $pass15 ? '✓' : '✗');

echo "\n[Test 16] getController() 按类名查找\n";
echo "----------------------------------------\n";
$ctrl16 = $mgr14->getController('Mycm\Annotations\Tests\Fixtures\Controllers\UserController');
$pass16 = $ctrl16 !== null && $ctrl16->title === '用户管理';
printf("getController() 找到控制器 [%s]: %s\n", $ctrl16?->title ?? 'null', $pass16 ? '✓' : '✗');

echo "\n[Test 17] scan() 切换路径到 Apps/admin\n";
echo "----------------------------------------\n";
$ctrls17 = $mgr14->scan(__DIR__ . '/Fixtures/Apps/admin/controller');
$pass17 = count($ctrls17) === 1 && $ctrls17[0]->title === '管理员管理';
printf("scan(新路径) 切换扫描结果: %s\n", $pass17 ? '✓' : '✗');

echo "\n========================================\n";
echo "  测试完成!\n";
echo "========================================\n";
