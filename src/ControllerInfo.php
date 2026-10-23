<?php

namespace Mycm\Annotations;

/**
 * 控制器扫描结果实体
 *
 * 包含一个被扫描到的控制器的完整信息。
 */
class ControllerInfo
{
	/**
	 * 控制器完全限定类名，如 "app\admin\controller\system\Admin"
	 */
	public string $className;

	/**
	 * 控制器标题，来源于注解
	 */
	public string $title;

	/**
	 * 控制器文件路径
	 */
	public string $filePath;

	/**
	 * 该控制器下的节点列表
	 *
	 * @var NodeInfo[]
	 */
	public array $nodes = [];

	/**
	 * @param string      $className 完全限定类名
	 * @param string      $title     控制器标题
	 * @param string      $filePath  文件路径
	 * @param NodeInfo[]  $nodes     节点列表
	 */
	public function __construct(
		string $className = '',
		string $title = '',
		string $filePath = '',
		array $nodes = []
	) {
		$this->className = $className;
		$this->title     = $title;
		$this->filePath  = $filePath;
		$this->nodes     = $nodes;
	}

	/**
	 * 将控制器信息转为数组
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'class_name' => $this->className,
			'title'      => $this->title,
			'file_path'  => $this->filePath,
			'nodes'      => array_map(fn(NodeInfo $node) => $node->toArray(), $this->nodes),
		];
	}
}
