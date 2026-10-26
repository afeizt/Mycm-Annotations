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

#[Controller(title: '管理员管理')]
class AdminController
{
	#[Node(title: '列表')]
	public function index(): void {}

	#[Node(title: '新增')]
	public function add(): void {}

	#[Node(title: '删除', auth: false)]
	public function delete(): void {}

	public function _before_index(): void {}
}
