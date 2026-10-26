<?php

/**
 * MyCm Annotations
 *
 * @author   afeizt@hotmail.com
 * @license  MIT https://mit-license.org
 * @link     https://github.com/afeizt/mycm-annotations
 */

namespace Mycm\Annotations\Attributes;

use Attribute;

/**
 * 节点/方法注解 - PHP 8 原生 Attribute
 *
 * 用于标记控制器中的方法为功能节点。
 * 兼容旧版 @NodeAnnotation(title="...") DocBlock 格式。
 *
 * 用法：
 *   #[Node(title: '列表')]                          // 默认 auth: true
 *   #[Node(title: '公开接口', auth: false)]          // 公开，无需鉴权
 *   #[Node(title: '删除', auth: ['rbac' => true])]   // 需要 RBAC 节点权限
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Node
{
	/**
	 * @param string       $title 节点标题
	 * @param bool|array   $auth  鉴权标记，true=需要鉴权, false=公开, array=多维度配置(login/rbac)
	 */
	public function __construct(
		public string $title = '',
		public mixed  $auth = true,
	) {}
}
