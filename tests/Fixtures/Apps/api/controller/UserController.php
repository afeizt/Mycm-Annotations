<?php

namespace Mycm\Annotations\Tests\Fixtures\Apps\api\controller;

/**
 * @ControllerAnnotation(title="用户接口")
 */
class UserController
{
	/**
	 * @NodeAnotation(title="获取用户信息")
	 */
	public function info() {}

	/**
	 * @NodeAnotation(title="用户登录")
	 */
	public function login() {}
}
