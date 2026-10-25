<?php

/**
 * MyCm Annotations
 *
 * @author   afeizt@hotmail.com
 * @license  MIT https://mit-license.org
 * @link     https://github.com/afeizt/mycm-annotations
 */

namespace Mycm\Annotations;

/**
 * 节点信息实体
 *
 * 表示控制器中单个方法节点的元数据，包含节点路径、标题、鉴权标记等。
 * 同时支持控制器级节点（type=1）和方法级节点（type=2）。
 */
class NodeInfo
{
	/** 方法名 */
	public string $methodName;
	/** 节点标题（来源于 Attribute） */
	public string $title;
	/** 所属控制器完全限定类名 */
	public string $controllerClass;
	/** 是否需要权限验证 */
	public bool $isAuth;
	/** 节点类型：1=控制器级节点，2=方法级节点 */
	public int $type;
	/** 旧版兼容的控制器节点路径（如 "system.admin"） */
	public string $controllerNode;

	public function __construct(
		string $methodName = '',
		string $title = '',
		string $controllerClass = '',
		bool   $isAuth = true,
		int    $type = 2,
		string $controllerNode = '',
	) {
		$this->methodName      = $methodName;
		$this->title           = $title;
		$this->controllerClass = $controllerClass;
		$this->isAuth          = $isAuth;
		$this->type            = $type;
		$this->controllerNode  = $controllerNode;
	}

	/**
	 * 生成节点路径字符串
	 *
	 * - type=1（控制器节点）：返回 controllerNode（如 "system.admin"）
	 * - type=2（方法节点）：controllerNode + "/" + methodName（如 "system.admin/index"）
	 *   若无 controllerNode 则退化为 完整类名转路径 + 方法名
	 */
	public function getNode(): string
	{
		if ($this->type === 1) {
			return $this->controllerNode;
		}
		if ($this->controllerNode !== '') {
			return $this->controllerNode . '/' . $this->methodName;
		}
		return str_replace('\\', '/', $this->controllerClass) . '/' . $this->methodName;
	}

	public function toArray(): array
	{
		return [
			'node'             => $this->getNode(),
			'title'            => $this->title,
			'is_auth'          => $this->isAuth,
			'type'             => $this->type,
			'method_name'      => $this->methodName,
			'controller_class' => $this->controllerClass,
		];
	}
}
