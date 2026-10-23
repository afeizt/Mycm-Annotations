<?php

namespace Mycm\Annotations;

/**
 * 控制器节点/方法扫描结果实体
 *
 * 包含一个被扫描到的控制器方法的完整信息。
 */
class NodeInfo
{
	/**
	 * 方法名称，如 "index"
	 */
	public string $methodName;

	/**
	 * 节点标题，来源于注解
	 */
	public string $title;

	/**
	 * 所属控制器完全限定类名
	 */
	public string $controllerClass;

	/**
	 * 是否需要权限验证，默认 true
	 */
	public bool $isAuth;

	/**
	 * @param string $methodName      方法名称
	 * @param string $title           节点标题
	 * @param string $controllerClass 所属控制器类名
	 * @param bool   $isAuth          是否需要权限验证
	 */
	public function __construct(
		string $methodName = '',
		string $title = '',
		string $controllerClass = '',
		bool   $isAuth = true,
	) {
		$this->methodName      = $methodName;
		$this->title           = $title;
		$this->controllerClass = $controllerClass;
		$this->isAuth          = $isAuth;
	}

	/**
	 * 获取节点唯一标识符
	 *
	 * 格式为 "控制器类名(斜杠分隔)/方法名"，与 EasyAdmin NodeService 的 node 字段格式兼容。
	 * 示例：app/admin/controller/Admin/index
	 *
	 * @return string
	 */
	public function getNode(): string
	{
		return str_replace('\\', '/', $this->controllerClass) . '/' . $this->methodName;
	}

	/**
	 * 将节点信息转为数组（与 EasyAdmin NodeService::getNodelist() 输出格式兼容）
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'node'             => $this->getNode(),
			'title'            => $this->title,
			'is_auth'          => $this->isAuth,
			'method_name'      => $this->methodName,
			'controller_class' => $this->controllerClass,
		];
	}
}
