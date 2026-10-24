<?php

namespace Mycm\Annotations;

use Mycm\Annotations\Attributes\Controller;
use Mycm\Annotations\Attributes\Node;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RegexIterator;

use const T_CLASS;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_STRING;
use const T_WHITESPACE;

/**
 * 控制器注解扫描器
 *
 * 自动扫描指定目录下的控制器文件，通过 PHP 8 原生 Attribute 提取控制器注解和节点方法注解。
 *
 * 使用示例：
 *   $scanner = new Scanner();
 *   $controllers = $scanner->scan('/path/to/controllers', ['namespace_prefix' => 'app\admin\controller']);
 *   foreach ($controllers as $ctrl) {
 *       echo $ctrl->title;
 *       foreach ($ctrl->nodes as $node) {
 *           echo $node->title;
 *       }
 *   }
 */
class Scanner
{
	/**
	 * 扫描结果缓存
	 *
	 * @var ControllerInfo[]
	 */
	private array $controllers = [];

	/**
	 * 当前扫描选项
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	public function __construct() {}

	/**
	 * 扫描指定目录下的所有控制器
	 *
	 * 会递归扫描目录中的所有 .php 文件，识别带有控制器注解的类及其节点方法。
	 *
	 * @param string $directory 要扫描的控制器目录绝对路径
	 * @param array  $options   扫描选项
	 *                          - 'exclude'                  string[] 排除的文件名模式（glob）
	 *                          - 'base_namespace'           string   基础命名空间，用于拼接完整类名
	 *                          - 'namespace_prefix'         string   旧版 EasyAdmin 兼容的命名空间前缀
	 *                                                               用于剥离前缀生成旧格式 node 路径
	 *                          - 'controller_attribute_class' string  控制器级 PHP 8 Attribute 类名，默认 Mycm\Annotations\Attributes\Controller
	 *                          - 'node_attribute_class'      string  方法级 PHP 8 Attribute 类名，默认 Mycm\Annotations\Attributes\Node
	 *                          - 'auth_field_name'            string  Attribute 中鉴权字段名，默认 'is_auth'（EasyAdmin 传 'auth'）
	 * @return ControllerInfo[] 扫描到的控制器信息列表
	 */
	public function scan(string $directory, array $options = []): array
	{
		$this->controllers = [];
		$this->options = $options;

		if (! is_dir($directory)) {
			return [];
		}

		$namespacePrefix = $options['namespace_prefix'] ?? '';
		$files = $this->findPhpFiles($directory, $options['exclude'] ?? []);

		foreach ($files as $filePath) {
			$controller = $this->processFile($filePath, $namespacePrefix);
			if ($controller !== null) {
				$this->controllers[] = $controller;
			}
		}

		return $this->controllers;
	}

	/**
	 * 获取已扫描的控制器列表
	 *
	 * @return ControllerInfo[]
	 */
	public function getControllers(): array
	{
		return $this->controllers;
	}

	/**
	 * 获取所有节点列表（按控制器分组）
	 *
	 * @return array<string, NodeInfo[]> 以控制器类名为 key 的节点分组
	 */
	public function getNodes(): array
	{
		$result = [];
		foreach ($this->controllers as $controller) {
			$result[$controller->className] = $controller->nodes;
		}
		return $result;
	}

	/**
	 * 获取所有节点的扁平列表（包含控制器节点 type=1 和方法节点 type=2）
	 *
	 * @return NodeInfo[]
	 */
	public function getAllNodes(): array
	{
		$nodes = [];
		foreach ($this->controllers as $controller) {
			// 添加控制器级节点（type=1）
			if ($controller->controllerNode !== '') {
				$nodes[] = new NodeInfo(
					methodName: '',
					title: $controller->title,
					controllerClass: $controller->className,
					isAuth: true,
					type: 1,
					controllerNode: $controller->controllerNode,
				);
			}
			// 添加方法级节点（type=2）
			foreach ($controller->nodes as $node) {
				$nodes[] = $node;
			}
		}
		return $nodes;
	}

	/**
	 * 根据控制器类名查找控制器信息
	 *
	 * @param string $className 完全限定类名
	 * @return ControllerInfo|null
	 */
	public function getController(string $className): ?ControllerInfo
	{
		foreach ($this->controllers as $controller) {
			if ($controller->className === $className) {
				return $controller;
			}
		}
		return null;
	}

	/**
	 * 递归查找目录下所有 PHP 文件
	 *
	 * @param string   $directory 目录路径
	 * @param string[] $exclude   排除的文件名模式
	 * @return string[] PHP 文件路径列表
	 */
	private function findPhpFiles(string $directory, array $exclude = []): array
	{
		$files = [];

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
			);

			$phpFiles = new RegexIterator($iterator, '/\.php$/i', RegexIterator::MATCH);

			foreach ($phpFiles as $file) {
				$filePath = $file->getRealPath();

				// 检查排除规则
				$fileName = $file->getFilename();
				$excluded = false;
				foreach ($exclude as $pattern) {
					if (fnmatch($pattern, $fileName)) {
						$excluded = true;
						break;
					}
				}

				if (! $excluded) {
					$files[] = $filePath;
				}
			}
		} catch (\Throwable $e) {
			// 目录无法访问时返回空数组
		}

		return $files;
	}

	/**
	 * 处理单个 PHP 文件，尝试提取控制器信息
	 *
	 * @param string $filePath        文件绝对路径
	 * @param string $namespacePrefix 旧版兼容的命名空间前缀
	 * @return ControllerInfo|null
	 */
	private function processFile(string $filePath, string $namespacePrefix = ''): ?ControllerInfo
	{
		// 1. 从文件内容解析类信息（不执行文件）
		$classInfo = $this->extractClassInfo($filePath);
		if ($classInfo === null) {
			return null;
		}

		$className = $classInfo['full_class'];

		// 2. 尝试通过反射获取控制器信息
		$reflectionClass = $this->safeReflect($className);

		if ($reflectionClass !== null) {
			return $this->processByReflection($reflectionClass, $filePath, $namespacePrefix);
		}

		// 3. 反射失败，无法读取 Attribute，返回 null
		return null;
	}

	/**
	 * 安全地获取反射类，失败返回 null
	 *
	 * @param string $className 完全限定类名
	 * @return ReflectionClass|null
	 */
	private function safeReflect(string $className): ?ReflectionClass
	{
		try {
			if (! class_exists($className, true)) {
				return null;
			}

			$reflection = new ReflectionClass($className);

			// 跳过抽象类、接口、Trait
			if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
				return null;
			}

			return $reflection;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * 通过反射处理控制器
	 *
	 * @param ReflectionClass $reflectionClass 反射类
	 * @param string          $filePath        文件路径
	 * @param string          $namespacePrefix 旧版兼容的命名空间前缀
	 * @return ControllerInfo|null
	 */
	private function processByReflection(ReflectionClass $reflectionClass, string $filePath, string $namespacePrefix = ''): ?ControllerInfo
	{
		$className = $reflectionClass->getName();

		// 1. 读取 PHP 8 Attribute
		$title = $this->getAttributeTitle($reflectionClass);

		// 2. 无 Attribute 则不是控制器
		if ($title === null) {
			return null;
		}

		// 3. 计算旧版兼容的控制器节点路径
		$controllerNode = $namespacePrefix !== ''
			? ControllerInfo::formatControllerNode($className, $namespacePrefix)
			: '';

		// 4. 扫描节点方法
		$nodes = [];
		$methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

		foreach ($methods as $method) {
			// 跳过魔术方法
			if (str_starts_with($method->getName(), '__')) {
				continue;
			}

			$nodeInfo = $this->processMethodByReflection($method, $className, $controllerNode);
			if ($nodeInfo !== null) {
				$nodes[] = $nodeInfo;
			}
		}

		return new ControllerInfo(
			className: $className,
			title: $title,
			filePath: $filePath,
			nodes: $nodes,
			controllerNode: $controllerNode
		);
	}

	/**
	 * 从反射类中读取 PHP 8 Attribute 的 title
	 *
	 * @param ReflectionClass $reflectionClass
	 * @return string|null
	 */
	private function getAttributeTitle(ReflectionClass $reflectionClass): ?string
	{
		$attributeClass = $this->options['controller_attribute_class'] ?? Controller::class;
		$attributes = $reflectionClass->getAttributes($attributeClass);

		if (empty($attributes)) {
			return null;
		}

		$instance = $attributes[0]->newInstance();
		return $instance->title !== '' ? $instance->title : null;
	}

	/**
	 * 通过反射处理方法，提取节点信息
	 *
	 * @param ReflectionMethod $method         反射方法
	 * @param string           $className      所属控制器类名
	 * @param string           $controllerNode 旧版兼容的控制器节点路径
	 * @return NodeInfo|null
	 */
	private function processMethodByReflection(ReflectionMethod $method, string $className, string $controllerNode = ''): ?NodeInfo
	{
		$methodName = $method->getName();

		// 1. 读取 PHP 8 Attribute
		$nodeData = $this->getMethodAttributeData($method);

		// 2. 无 Attribute 则不是节点
		if ($nodeData === null) {
			return null;
		}

		$title  = $nodeData['title'];
		$isAuth = $nodeData['is_auth'];

		return new NodeInfo(
			methodName: $methodName,
			title: $title,
			controllerClass: $className,
			isAuth: $isAuth,
			type: 2,
			controllerNode: $controllerNode,
		);
	}

	/**
	 * 从反射方法中读取 PHP 8 Attribute 的完整数据
	 *
	 * @param ReflectionMethod $method
	 * @return array{title: string, is_auth: bool}|null
	 */
	private function getMethodAttributeData(ReflectionMethod $method): ?array
	{
		$attributeClass = $this->options['node_attribute_class'] ?? Node::class;
		$attributes = $method->getAttributes($attributeClass);

		if (empty($attributes)) {
			return null;
		}

		$instance = $attributes[0]->newInstance();
		$authFieldName = $this->options['auth_field_name'] ?? 'is_auth';
		return [
			'title'   => $instance->title,
			'is_auth' => $instance->$authFieldName ?? true,
		];
	}

	/**
	 * 从 PHP 文件内容中提取命名空间和类名
	 *
	 * 使用 PHP 内置 tokenizer，不执行文件代码。
	 *
	 * @param string $filePath 文件绝对路径
	 * @return array{namespace: string, class: string, full_class: string}|null
	 */
	private function extractClassInfo(string $filePath): ?array
	{
		$content = file_get_contents($filePath);
		if ($content === false) {
			return null;
		}

		$tokens = token_get_all($content);

		$namespace  = '';
		$className  = '';

		$tokenCount = count($tokens);
		for ($i = 0; $i < $tokenCount; $i++) {
			$token = $tokens[$i];

			if (! is_array($token)) {
				continue;
			}

			// 解析命名空间
			if ($token[0] === T_NAMESPACE) {
				$namespace = $this->extractNamespace($tokens, $i, $tokenCount);
			}

			// 解析类名
			if ($token[0] === T_CLASS) {
				$className = $this->extractClassName($tokens, $i, $tokenCount);
				if ($className !== '') {
					break; // 找到第一个类就退出
				}
			}
		}

		if ($className === '') {
			return null;
		}

		return [
			'namespace'   => $namespace,
			'class'       => $className,
			'full_class'  => $namespace ? $namespace . '\\' . $className : $className,
		];
	}

	/**
	 * 从 token 流中提取命名空间字符串
	 *
	 * @param array[] $tokens     token 数组
	 * @param int     $startIndex T_NAMESPACE 所在的索引
	 * @param int     $tokenCount token 总数
	 * @return string
	 */
	private function extractNamespace(array $tokens, int $startIndex, int $tokenCount): string
	{
		$namespace = '';
		for ($j = $startIndex + 1; $j < $tokenCount; $j++) {
			$nsToken = $tokens[$j];

			if (! is_array($nsToken)) {
				if ($nsToken === ';' || $nsToken === '{') {
					break;
				}
				continue;
			}

			if (
				$nsToken[0] === T_STRING
				|| $nsToken[0] === T_NAME_QUALIFIED
				|| $nsToken[0] === T_NAME_FULLY_QUALIFIED
			) {
				if ($namespace !== '' && ! str_ends_with($namespace, '\\')) {
					$namespace .= '\\';
				}
				$namespace .= $nsToken[1];
			} elseif ($nsToken[0] === T_WHITESPACE) {
				continue;
			} else {
				// 其他 token（如 T_NS_SEPARATOR 在低版本 PHP 中出现）
				// 在 PHP 8.0+ 中 T_NAME_QUALIFIED 已包含完整的命名空间路径
				break;
			}
		}

		// 处理低版本 PHP（< 8.0）中的 T_NS_SEPARATOR
		if ($namespace === '') {
			$nsParts = [];
			for ($j = $startIndex + 1; $j < $tokenCount; $j++) {
				$nsToken = $tokens[$j];
				if (! is_array($nsToken)) {
					if ($nsToken === ';' || $nsToken === '{') {
						break;
					}
					continue;
				}
				if ($nsToken[0] === T_STRING) {
					$nsParts[] = $nsToken[1];
				} elseif ($nsToken[0] === T_NS_SEPARATOR) {
					// 跳过
				} elseif ($nsToken[0] === T_WHITESPACE) {
					continue;
				} else {
					break;
				}
			}
			$namespace = implode('\\', $nsParts);
		}

		return $namespace;
	}

	/**
	 * 从 token 流中提取类名
	 *
	 * @param array[] $tokens     token 数组
	 * @param int     $startIndex T_CLASS 所在的索引
	 * @param int     $tokenCount token 总数
	 * @return string
	 */
	private function extractClassName(array $tokens, int $startIndex, int $tokenCount): string
	{
		for ($j = $startIndex + 1; $j < $tokenCount; $j++) {
			$classToken = $tokens[$j];

			if (! is_array($classToken)) {
				continue;
			}

			if ($classToken[0] === T_WHITESPACE) {
				continue;
			}

			if ($classToken[0] === T_STRING) {
				return $classToken[1];
			}

			// T_STRING 之前的非空白 token 不是类名
			break;
		}

		return '';
	}
}
