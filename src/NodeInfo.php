<?php

namespace Mycm\Annotations;

class NodeInfo
{
	public string $methodName;
	public string $title;
	public string $controllerClass;
	public bool $isAuth;
	public int $type;
	public string $controllerNode;

	public function __construct(
		string $methodName = '',
		string $title = '',
		string $controllerClass = '',
		bool   $isAuth = true,
		int    $type = 2,
		string $controllerNode = '',
	) {
		$this->methodName      = $methodName;
		$this->title           = $title;
		$this->controllerClass = $controllerClass;
		$this->isAuth          = $isAuth;
		$this->type            = $type;
		$this->controllerNode  = $controllerNode;
	}

	public function getNode(): string
	{
		if ($this->type === 1) {
			return $this->controllerNode;
		}
		if ($this->controllerNode !== '') {
			return $this->controllerNode . '/' . $this->methodName;
		}
		return str_replace('\\', '/', $this->controllerClass) . '/' . $this->methodName;
	}

	public function toArray(): array
	{
		return [
			'node'             => $this->getNode(),
			'title'            => $this->title,
			'is_auth'          => $this->isAuth,
			'type'             => $this->type,
			'method_name'      => $this->methodName,
			'controller_class' => $this->controllerClass,
		];
	}
}
