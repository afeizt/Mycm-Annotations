<?php

/**
 * MyCm Annotations
 *
 * @author   afeizt@hotmail.com
 * @license  MIT https://mit-license.org
 * @link     https://github.com/afeizt/mycm-annotations
 */

namespace Mycm\Annotations\Tests\Fixtures\Controllers;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;

/**
 * Class UserController
 *
 * 使用 PHP 8 原生 Attribute 的控制器示例
 */
#[Controller(title: '用户管理')]
class UserController
{
	#[Node(title: '用户列表')]
	public function index(): void {}

	#[Node(title: '添加用户', auth: false)]
	public function add(): void {}

	#[Node(title: '编辑用户')]
	public function edit(): void {}

	// 没有注解的方法，不会被识别为节点
	public function _initialize(): void {}
}
