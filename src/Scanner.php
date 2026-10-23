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
use const T_DOC_COMMENT;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_STRING;
use const T_WHITESPACE;

/**
 * 控制器注解扫描器
 *
 * 自动扫描指定目录下的控制器文件，提取控制器注解和节点方法注解。
 * 优先使用 PHP 8 原生 Attribute，自动回退到 DocBlock 格式。
 *
 * 使用示例：
 *   $scanner = new Scanner();
 *   $controllers = $scanner->scan('/path/to/controllers');
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
	 * DocBlock 解析器实例
	 */
	private DocBlockParser $docBlockParser;

	public function __construct()
	{
		$this->docBlockParser = new DocBlockParser();
	}

	/**
	 * 扫描指定目录下的所有控制器
	 *
	 * 会递归扫描目录中的所有 .php 文件，识别带有控制器注解的类及其节点方法。
	 *
	 * @param string $directory 要扫描的控制器目录绝对路径
	 * @param array  $options   扫描选项
	 *                          - 'exclude'  string[] 排除的文件名模式（glob）
	 *                          - 'base_namespace' string 基础命名空间，用于拼接完整类名
	 * @return ControllerInfo[] 扫描到的控制器信息列表
	 */
	public function scan(string $directory, array $options = []): array
	{
		$this->controllers = [];

		if (! is_dir($directory)) {
			return [];
		}

		$files = $this->findPhpFiles($directory, $options['exclude'] ?? []);

		foreach ($files as $filePath) {
			$controller = $this->processFile($filePath);
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
	 * 获取所有节点的扁平列表
	 *
	 * @return NodeInfo[]
	 */
	public function getAllNodes(): array
	{
		$nodes = [];
		foreach ($this->controllers as $controller) {
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
	 * @param string $filePath 文件绝对路径
	 * @return ControllerInfo|null
	 */
	private function processFile(string $filePath): ?ControllerInfo
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
			return $this->processByReflection($reflectionClass, $filePath);
		}

		// 3. 反射失败，使用文件内容直接解析 DocBlock（仅兼容旧版格式）
		return $this->processByFileContent($classInfo, $filePath);
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
	 * @return ControllerInfo|null
	 */
	private function processByReflection(ReflectionClass $reflectionClass, string $filePath): ?ControllerInfo
	{
		$className = $reflectionClass->getName();

		// 1. 优先读取 PHP 8 Attribute
		$title = $this->getAttributeTitle($reflectionClass);

		// 2. Attribute 未找到时回退到 DocBlock
		if ($title === null) {
			$docComment = $reflectionClass->getDocComment();
			$legacyData = $docComment ? $this->docBlockParser->parseControllerAnnotation($docComment) : null;
			if ($legacyData === null) {
				return null; // 不是控制器
			}
			$title = $legacyData['title'] ?? '';
		}

		// 3. 扫描节点方法
		$nodes = [];
		$methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

		foreach ($methods as $method) {
			// 跳过魔术方法和继承自父类的方法
			if (str_starts_with($method->getName(), '__')) {
				continue;
			}

			$nodeInfo = $this->processMethodByReflection($method, $className);
			if ($nodeInfo !== null) {
				$nodes[] = $nodeInfo;
			}
		}

		return new ControllerInfo(
			className: $className,
			title: $title,
			filePath: $filePath,
			nodes: $nodes
		);
	}

	/**
	 * 从反射类中读取 PHP 8 #[Controller] Attribute 的 title
	 *
	 * @param ReflectionClass $reflectionClass
	 * @return string|null
	 */
	private function getAttributeTitle(ReflectionClass $reflectionClass): ?string
	{
		$attributes = $reflectionClass->getAttributes(Controller::class);

		if (empty($attributes)) {
			return null;
		}

		$instance = $attributes[0]->newInstance();
		return $instance->title !== '' ? $instance->title : null;
	}

	/**
	 * 通过反射处理方法，提取节点信息
	 *
	 * @param ReflectionMethod $method     反射方法
	 * @param string           $className  所属控制器类名
	 * @return NodeInfo|null
	 */
	private function processMethodByReflection(ReflectionMethod $method, string $className): ?NodeInfo
	{
		$methodName = $method->getName();

		// 1. 优先读取 PHP 8 Attribute
		$nodeData = $this->getMethodAttributeData($method);

		// 2. Attribute 未找到时回退到 DocBlock
		if ($nodeData === null) {
			$docComment = $method->getDocComment();
			$legacyData = $docComment ? $this->docBlockParser->parseNodeAnnotation($docComment) : null;
			if ($legacyData === null) {
				return null;
			}
			$title  = $legacyData['title'] ?? '';
			$isAuth = $legacyData['is_auth'] ?? true;
		} else {
			$title  = $nodeData['title'];
			$isAuth = $nodeData['is_auth'];
		}

		return new NodeInfo(
			methodName: $methodName,
			title: $title,
			controllerClass: $className,
			isAuth: $isAuth,
		);
	}

	/**
	 * 从反射方法中读取 PHP 8 #[Node] Attribute 的完整数据
	 *
	 * @param ReflectionMethod $method
	 * @return array{title: string, is_auth: bool}|null
	 */
	private function getMethodAttributeData(ReflectionMethod $method): ?array
	{
		$attributes = $method->getAttributes(Node::class);

		if (empty($attributes)) {
			return null;
		}

		$instance = $attributes[0]->newInstance();
		return [
			'title'   => $instance->title,
			'is_auth' => $instance->is_auth,
		];
	}

	/**
	 * 通过文件内容直接解析（无反射时的回退方案，仅支持旧版 DocBlock 格式）
	 *
	 * @param array  $classInfo extractClassInfo() 的返回结果
	 * @param string $filePath  文件路径
	 * @return ControllerInfo|null
	 */
	private function processByFileContent(array $classInfo, string $filePath): ?ControllerInfo
	{
		$content = file_get_contents($filePath);
		if ($content === false) {
			return null;
		}

		$className = $classInfo['full_class'];

		// 尝试从内容中提取类级 DocBlock
		$classDocComment = $classInfo['doc_comment'] ?? '';
		$legacyData = $classDocComment
			? $this->docBlockParser->parseControllerAnnotation($classDocComment)
			: null;

		if ($legacyData === null) {
			return null;
		}

		// 该类是控制器，尝试解析方法级 DocBlock
		$nodes = $this->extractMethodAnnotationsFromContent($content, $className);

		return new ControllerInfo(
			className: $className,
			title: $legacyData['title'] ?? '',
			filePath: $filePath,
			nodes: $nodes
		);
	}

	/**
	 * 从文件内容中提取方法级的 @NodeAnotation
	 *
	 * @param string $content   文件内容
	 * @param string $className 控制器类名
	 * @return NodeInfo[]
	 */
	private function extractMethodAnnotationsFromContent(string $content, string $className): array
	{
		$nodes = [];

		// 匹配方法声明及其前面的 DocBlock
		$pattern = '/
            (\/\*\*.*?\*\/)\s*               # DocBlock (group 1)
            (?:public|protected|private)?\s*  # 可选访问修饰符
            function\s+(\w+)\s*\(             # function methodName(
        /sx';

		if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$docComment = $match[1];
				$methodName = $match[2];

				if (str_starts_with($methodName, '__')) {
					continue;
				}

				$legacyData = $this->docBlockParser->parseNodeAnnotation($docComment);
				if ($legacyData !== null) {
					$nodes[] = new NodeInfo(
						methodName: $methodName,
						title: $legacyData['title'] ?? '',
						controllerClass: $className,
						isAuth: $legacyData['is_auth'] ?? true,
					);
				}
			}
		}

		return $nodes;
	}

	/**
	 * 从 PHP 文件内容中提取命名空间、类名和 DocComment
	 *
	 * 使用 PHP 内置 tokenizer，不执行文件代码。
	 *
	 * @param string $filePath 文件绝对路径
	 * @return array{namespace: string, class: string, full_class: string, doc_comment: string}|null
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
		$docComment = '';

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

			// 记录 DocComment（类声明前面的最后一个注释）
			if ($token[0] === T_DOC_COMMENT) {
				$docComment = $token[1];
			}

			// 解析类名
			if ($token[0] === T_CLASS) {
				$className = $this->extractClassName($tokens, $i, $tokenCount);
				if ($className !== '') {
					break; // 找到第一个类就退出
				}
			}

			// 非 DocComment 的 token 清除之前的 docComment（如 T_WHITESPACE 不会触发，但属性注解等会）
			if ($token[0] !== T_DOC_COMMENT && $token[0] !== T_WHITESPACE) {
				// 仅在遇到 Attribute 时清除，保留对 docComment 的跟踪
				// 这里我们简单地在遇到非注释、非空白 token 时检查是否可能是中间的代码
				// 为了简化：只在遇到 T_CLASS 声明之前遇到的最后一个 T_DOC_COMMENT 有效
				// 如果之后遇到了非 T_DOC_COMMENT 且非 T_WHITESPACE 且非 T_CLASS 的 token（如 T_ATTRIBUTE），
				// 且它不是一个类声明相关的 token，则之前的 docComment 不属于类
			}
		}

		if ($className === '') {
			return null;
		}

		return [
			'namespace'   => $namespace,
			'class'       => $className,
			'full_class'  => $namespace ? $namespace . '\\' . $className : $className,
			'doc_comment' => $docComment,
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
