<?php

namespace Mycm\Annotations;

/**
 * DocBlock 兼容解析器
 *
 * 用于解析旧版 DocBlock 风格的注解格式：
 *   - @ControllerAnnotation(title="管理员管理")
 *   - @NodeAnotation(title="列表")
 *
 * 这使得从 doctrine/annotations 迁移时无需修改现有控制器代码。
 */
class DocBlockParser
{
	/**
	 * 从类 DocComment 中解析 @ControllerAnnotation
	 *
	 * 示例：
	 *   /**
	 *    * @ControllerAnnotation(title="管理员管理")
	 *    * /
	 *
	 * @param string $docComment 类的 DocComment 原始文本
	 * @return array{title: string}|null 解析成功返回 ['title' => '...']，否则返回 null
	 */
	public function parseControllerAnnotation(string $docComment): ?array
	{
		return $this->parseAnnotation($docComment, 'ControllerAnnotation');
	}

	/**
	 * 从方法 DocComment 中解析 @NodeAnotation
	 *
	 * 示例：
	 *   /**
	 *    * @NodeAnotation(title="列表")
	 *    * /
	 *
	 * @param string $docComment 方法的 DocComment 原始文本
	 * @return array{title: string}|null 解析成功返回 ['title' => '...']，否则返回 null
	 */
	public function parseNodeAnnotation(string $docComment): ?array
	{
		return $this->parseAnnotation($docComment, 'NodeAnotation');
	}

	/**
	 * 通用注解解析
	 *
	 * 支持以下格式：
	 *   @AnnotationName(title="value")
	 *   @AnnotationName(title='value')
	 *   @AnnotationName(title=value)
	 *
	 * @param string $docComment    DocComment 原始文本
	 * @param string $annotationName 要查找的注解名称
	 * @return array<string, mixed>|null
	 */
	private function parseAnnotation(string $docComment, string $annotationName): ?array
	{
		if (empty($docComment)) {
			return null;
		}

		$pattern = '/@' . preg_quote($annotationName, '/') . '\s*\(([^)]*)\)/s';

		if (! preg_match($pattern, $docComment, $matches)) {
			return null;
		}

		$paramsStr = $matches[1];
		$result    = [];

		// 解析键值对参数
		preg_match_all('/(\w+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^,\s]+))/', $paramsStr, $paramMatches, PREG_SET_ORDER);

		foreach ($paramMatches as $param) {
			$key   = $param[1];
			$value = $param[3] !== '' ? $param[3] : ($param[4] !== '' ? $param[4] : $param[5]);

			// 尝试类型转换
			$value = $this->castValue($value);

			$result[$key] = $value;
		}

		return ! empty($result) ? $result : null;
	}

	/**
	 * 值类型转换
	 *
	 * @param string $value 原始字符串值
	 * @return mixed
	 */
	private function castValue(string $value): mixed
	{
		// null
		if (strtolower($value) === 'null') {
			return null;
		}

		// 布尔值
		if (strtolower($value) === 'true') {
			return true;
		}
		if (strtolower($value) === 'false') {
			return false;
		}

		// 整数
		if (is_numeric($value) && strpos($value, '.') === false) {
			return (int) $value;
		}

		// 浮点数
		if (is_numeric($value)) {
			return (float) $value;
		}

		return $value;
	}
}
