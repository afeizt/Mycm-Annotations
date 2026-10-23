<?php

namespace Mycm\Annotations;

/**
 * 应用扫描结果实体
 *
 * 表示单个应用（如 admin、api）的扫描结果，包含该应用下所有控制器及其节点。
 */
class AppScanResult
{
	/**
	 * 应用名称，如 "admin"、"api"、"index"
	 */
	public string $appName;

	/**
	 * 控制器目录路径
	 */
	public string $controllerPath;

	/**
	 * 该应用下的控制器列表
	 *
	 * @var ControllerInfo[]
	 */
	public array $controllers = [];

	/**
	 * @param string           $appName        应用名称
	 * @param string           $controllerPath 控制器目录路径
	 * @param ControllerInfo[] $controllers    控制器列表
	 */
	public function __construct(
		string $appName = '',
		string $controllerPath = '',
		array $controllers = []
	) {
		$this->appName        = $appName;
		$this->controllerPath = $controllerPath;
		$this->controllers    = $controllers;
	}

	/**
	 * 获取该应用下所有节点总数
	 */
	public function getTotalNodes(): int
	{
		$count = 0;
		foreach ($this->controllers as $controller) {
			$count += count($controller->nodes);
		}
		return $count;
	}

	/**
	 * 获取该应用下控制器数量
	 */
	public function getTotalControllers(): int
	{
		return count($this->controllers);
	}

	/**
	 * 转为数组
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'app_name'         => $this->appName,
			'controller_path'  => $this->controllerPath,
			'total_controllers' => $this->getTotalControllers(),
			'total_nodes'      => $this->getTotalNodes(),
			'controllers'      => array_map(fn(ControllerInfo $c) => $c->toArray(), $this->controllers),
		];
	}
}
