<?php

namespace Mycm\Annotations\Tests\Fixtures\Apps\admin\controller;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;

#[Controller(title: "管理员管理")]
class SystemController
{
	#[Node(title: "列表")]
	public function index() {}

	#[Node(title: "新增")]
	public function add() {}

	#[Node(title: "删除")]
	public function delete() {}
}
