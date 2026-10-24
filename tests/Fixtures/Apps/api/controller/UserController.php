<?php

namespace Mycm\Annotations\Tests\Fixtures\Apps\api\controller;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;

#[Controller(title: "用户接口")]
class UserController
{
	#[Node(title: "获取用户信息")]
	public function info() {}

	#[Node(title: "用户登录")]
	public function login() {}
}
