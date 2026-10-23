<?php

namespace Mycm\Annotations\Attributes;

use Attribute;

/**
 * 控制器注解 - PHP 8 原生 Attribute
 *
 * 用于标记一个类为控制器，并提供控制器标题信息。
 * 兼容旧版 @ControllerAnnotation(title="...") DocBlock 格式。
 *
 * 用法：
 *   #[Controller(title: "管理员管理")]
 *   class Admin { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
	/**
	 * @param string $title 控制器标题
	 */
	public function __construct(
		public string $title = ''
	) {}
}
