<?php

/**
 * MyCm Annotations
 *
 * @author   afeizt@hotmail.com
 * @license  MIT https://mit-license.org
 * @link     https://github.com/afeizt/mycm-annotations
 */

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
	 * 旧版 EasyAdmin 兼容的控制器节点路径，如 "system.admin"
	 */
	public string $controllerNode;

	/**
	 * 该控制器下的节点列表
	 *
	 * @var NodeInfo[]
	 */
	public array $nodes = [];

	/**
	 * @param string      $className      完全限定类名
	 * @param string      $title          控制器标题
	 * @param string      $filePath       文件路径
	 * @param NodeInfo[]  $nodes          节点列表
	 * @param string      $controllerNode 旧版兼容的控制器节点路径
	 */
	public function __construct(
		string $className = '',
		string $title = '',
		string $filePath = '',
		array $nodes = [],
		string $controllerNode = ''
	) {
		$this->className      = $className;
		$this->title          = $title;
		$this->filePath       = $filePath;
		$this->nodes          = $nodes;
		$this->controllerNode = $controllerNode;
	}

	/**
	 * 将完整类名转换为旧版 EasyAdmin 兼容的控制器节点路径
	 *
	 * 例如：app\admin\controller\system\Admin → system.admin
	 *
	 * @param string $className       完全限定类名
	 * @param string $namespacePrefix 命名空间前缀（要剥离的部分）
	 * @return string 旧版兼容的控制器节点路径
	 */
	public static function formatControllerNode(string $className, string $namespacePrefix): string
	{
		$prefix = rtrim($namespacePrefix, '\\') . '\\';

		if (str_starts_with($className, $prefix)) {
			$relative = substr($className, strlen($prefix));
		} else {
			$relative = $className;
		}

		$parts = explode('\\', $relative);
		$classShortName = array_pop($parts);
		$subDirs = $parts;

		$classNodeName = self::humpToLine(lcfirst($classShortName));

		if (empty($subDirs)) {
			return $classNodeName;
		}

		return implode('.', $subDirs) . '.' . $classNodeName;
	}

	/**
	 * 驼峰转下划线
	 *
	 * @param string $str 驼峰格式字符串
	 * @return string 下划线格式字符串
	 */
	private static function humpToLine(string $str): string
	{
		$str = preg_replace('/([A-Z])/', '_$1', $str);
		$str = strtolower($str);
		return ltrim($str, '_');
	}

	/**
	 * 将控制器信息转为数组
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'class_name'      => $this->className,
			'title'           => $this->title,
			'file_path'       => $this->filePath,
			'controller_node' => $this->controllerNode,
			'nodes'           => array_map(fn(NodeInfo $node) => $node->toArray(), $this->nodes),
		];
	}
}
