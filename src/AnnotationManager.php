<?php

namespace Mycm\Annotations;

/**
 * 注解管理器 - 单 / 多应用可配置入口
 *
 * 支持两种用法：
 *   1. 单应用：$manager = new AnnotationManager('/path/to/admin/controller');
 *      $controllers = $manager->scan();
 *   2. 多应用：$manager = new AnnotationManager();
 *      $manager->addApp('admin', '...')->addApp('api', '...');
 *      $results = $manager->scanAll();
 */
class AnnotationManager
{
	/**
	 * 多应用配置列表 ['应用名' => '控制器目录路径']
	 *
	 * @var array<string, string>
	 */
	private array $apps = [];

	/**
	 * 当前单应用控制器目录路径
	 */
	private ?string $appPath = null;

	/**
	 * 扫描器实例
	 */
	private Scanner $scanner;

	/**
	 * 扫描选项
	 *
	 * @var array<string, mixed>
	 */
	private array $scanOptions = [];

	/**
	 * 最近一次 scan() 的扫描结果缓存
	 *
	 * @var ControllerInfo[]|null
	 */
	private ?array $lastScanResult = null;

	/**
	 * @param string|null  $appPath 可选，指定当前应用的控制器目录路径
	 * @param Scanner|null $scanner 可选，注入 Scanner 实例
	 */
	public function __construct(?string $appPath = null, ?Scanner $scanner = null)
	{
		$this->appPath = $appPath;
		$this->scanner = $scanner ?? new Scanner();
	}

	// ==================== 单应用便捷 API ====================

	/**
	 * 设置（或切换）当前应用的控制器目录路径
	 *
	 * @param string $path 控制器目录绝对路径
	 * @return $this
	 */
	public function setAppPath(string $path): self
	{
		$this->appPath = $path;
		$this->lastScanResult = null;
		return $this;
	}

	/**
	 * 获取当前设置的控制器目录路径
	 */
	public function getAppPath(): ?string
	{
		return $this->appPath;
	}

	/**
	 * 扫描当前应用路径下的所有控制器
	 *
	 * 可通过 $path 参数临时覆盖已配置的路径。
	 *
	 * @param string|null $path 控制器目录路径，传 null 则使用 setAppPath() 或构造函数设置的路径
	 * @return ControllerInfo[]
	 */
	public function scan(?string $path = null): array
	{
		$targetPath = $path ?? $this->appPath;

		if ($targetPath === null || ! is_dir($targetPath)) {
			$this->lastScanResult = [];
			return [];
		}

		$this->lastScanResult = $this->scanner->scan($targetPath, $this->scanOptions);
		return $this->lastScanResult;
	}

	/**
	 * 获取扫描到的控制器列表（不重新扫描，使用缓存）
	 *
	 * 如果尚未扫描，会自动触发一次扫描。
	 *
	 * @return ControllerInfo[]
	 */
	public function getControllers(): array
	{
		if ($this->lastScanResult === null) {
			$this->scan();
		}
		return $this->lastScanResult ?? [];
	}

	/**
	 * 获取扫描到的所有节点（扁平列表）
	 *
	 * @return NodeInfo[]
	 */
	public function getNodes(): array
	{
		$nodes = [];
		foreach ($this->getControllers() as $controller) {
			foreach ($controller->nodes as $node) {
				$nodes[] = $node;
			}
		}
		return $nodes;
	}

	/**
	 * 按控制器类名查找
	 *
	 * @param string $className 完全限定类名
	 * @return ControllerInfo|null
	 */
	public function getController(string $className): ?ControllerInfo
	{
		foreach ($this->getControllers() as $controller) {
			if ($controller->className === $className) {
				return $controller;
			}
		}
		return null;
	}

	// ==================== 多应用 API ====================

	/**
	 * 添加一个应用的控制器路径（多应用模式）
	 *
	 * @param string $name           应用名称，如 "admin"、"api"
	 * @param string $controllerPath 控制器目录绝对路径
	 * @return $this
	 */
	public function addApp(string $name, string $controllerPath): self
	{
		$this->apps[$name] = $controllerPath;
		return $this;
	}

	/**
	 * 批量设置多应用配置
	 *
	 * @param array<string, string> $apps ['应用名' => '路径']
	 * @return $this
	 */
	public function setApps(array $apps): self
	{
		$this->apps = $apps;
		return $this;
	}

	/**
	 * 移除某个应用配置
	 */
	public function removeApp(string $name): self
	{
		unset($this->apps[$name]);
		return $this;
	}

	/**
	 * 获取已配置的应用列表
	 *
	 * @return array<string, string>
	 */
	public function getApps(): array
	{
		return $this->apps;
	}

	/**
	 * 扫描所有已配置的应用，返回按应用分组的结果
	 *
	 * @return AppScanResult[]
	 */
	public function scanAll(): array
	{
		$results = [];
		foreach ($this->apps as $appName => $controllerPath) {
			$result = $this->scanApp($appName);
			if ($result !== null) {
				$results[] = $result;
			}
		}
		return $results;
	}

	/**
	 * 扫描单个已配置的应用
	 */
	public function scanApp(string $appName): ?AppScanResult
	{
		if (! isset($this->apps[$appName])) {
			return null;
		}
		$controllerPath = $this->apps[$appName];
		if (! is_dir($controllerPath)) {
			return new AppScanResult(appName: $appName, controllerPath: $controllerPath, controllers: []);
		}
		$controllers = $this->scanner->scan($controllerPath, $this->scanOptions);
		return new AppScanResult(appName: $appName, controllerPath: $controllerPath, controllers: $controllers);
	}

	/**
	 * 扫描所有应用，返回控制器的扁平列表
	 *
	 * @return ControllerInfo[]
	 */
	public function getAllControllers(): array
	{
		$all = [];
		foreach ($this->scanAll() as $app) {
			foreach ($app->controllers as $c) {
				$all[] = $c;
			}
		}
		return $all;
	}

	/**
	 * 扫描所有应用，返回节点的扁平列表
	 *
	 * @return NodeInfo[]
	 */
	public function getAllNodes(): array
	{
		$all = [];
		foreach ($this->scanAll() as $app) {
			foreach ($app->controllers as $c) {
				foreach ($c->nodes as $n) {
					$all[] = $n;
				}
			}
		}
		return $all;
	}

	/**
	 * 按应用分组返回节点，兼容 EasyAdmin NodeService::getNodelist() 格式
	 *
	 * @return array<string, array<array<string, mixed>>>
	 */
	public function getAllNodesGroupedByApp(): array
	{
		$result = [];
		foreach ($this->scanAll() as $appResult) {
			$appNodes = [];
			foreach ($appResult->controllers as $controller) {
				foreach ($controller->nodes as $node) {
					$appNodes[] = [
						'node'             => $node->getNode(),
						'title'            => $node->title,
						'is_auth'          => $node->isAuth,
						'method_name'      => $node->methodName,
						'controller_class' => $controller->className,
						'controller_title' => $controller->title,
					];
				}
			}
			$result[$appResult->appName] = $appNodes;
		}
		return $result;
	}

	/**
	 * 扫描全部，以数组格式输出
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return array_map(fn(AppScanResult $r) => $r->toArray(), $this->scanAll());
	}

	/**
	 * 设置扫描选项
	 *
	 * @param array<string, mixed> $options
	 * @return $this
	 */
	public function setScanOptions(array $options): self
	{
		$this->scanOptions = $options;
		return $this;
	}
}
