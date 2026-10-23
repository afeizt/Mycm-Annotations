<?php

namespace Mycm\Annotations\Attributes;

use Attribute;

/**
 * 节点/方法注解 - PHP 8 原生 Attribute
 *
 * 用于标记控制器中的方法为功能节点，并提供节点标题信息。
 * 兼容旧版 @NodeAnotation(title="...") DocBlock 格式。
 *
 * 用法：
 *   #[Node(title: "列表")]
 *   public function index() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Node
{
	/**
	 * @param string $title   节点标题
	 * @param bool   $is_auth 是否需要权限验证，默认 true
	 */
	public function __construct(
		public string $title = '',
		public bool   $is_auth = true,
	) {}
}
