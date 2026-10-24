<?php

namespace Mycm\Annotations\Tests\Fixtures\Controllers;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;

#[Controller(title: '配置管理')]
class ConfigController
{
	#[Node(title: '系统配置')]
	public function index(): void {}

	#[Node(title: '邮件设置')]
	public function mail(): void {}

	public function noAnnotation(): void {}
}
