<?php

namespace Mycm\Annotations\Tests\Fixtures\Controllers;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;

/**
 * 混合模式：同时有 DocBlock 和 PHP 8 Attribute
 * PHP 8 Attribute 优先级更高
 */
#[Controller(title: '配置管理')]
class ConfigController
{
	/**
	 * @NodeAnotation(title="旧版标题-会被忽略")
	 */
	#[Node(title: '系统配置')]
	public function index(): void {}

	/**
	 * @NodeAnotation(title="邮件设置")
	 */
	public function mail(): void {}

	public function noAnnotation(): void {}
}
