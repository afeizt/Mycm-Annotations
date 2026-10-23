<?php

namespace Mycm\Annotations\Tests\Fixtures\Controllers;

/**
 * Class Admin
 * @package app\admin\controller\system
 * @ControllerAnnotation(title="管理员管理")
 *
 * 使用旧版 DocBlock 注解的控制器示例（兼容模式）
 */
class AdminController
{
	/**
	 * @NodeAnotation(title="列表")
	 */
	public function index(): void {}

	/**
	 * @NodeAnotation(title="新增")
	 */
	public function add(): void {}

	/**
	 * @NodeAnotation(title="删除", is_auth=0)
	 */
	public function delete(): void {}

	// 没有注解的方法，不会被识别为节点
	public function _before_index(): void {}
}
