<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Traits;

use InvalidArgumentException;
use function call_user_func;
use function count;
use function function_exists;

/**
 * 简单的参数验证工具
 */
trait HelperMethods
{
    /** @see PackageTrait::staticFilter() */
    public function filter(array $input): array
    {
        return self::staticFilter($input);
    }

    /**
     * @param array $input
     * @return array
     */
    public static function staticFilter(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if ($value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array $options
     * @param array $validators
     * @return void
     * @throws InvalidArgumentException
     */
    public function verify(array $options, array $validators): void
    {
        self::staticVerify($options, $validators);
    }

    /**
     * @param array $options
     * @param array $validators = [
     *      ['serviceName', 'is_string', true],
     *      ['port', 'is_int', false]
     * ]
     * @return void
     * @throws InvalidArgumentException
     */
    public static function staticVerify(mixed $options, array $validators): void
    {
        if (!is_array($options)) {
            throw new InvalidArgumentException('Invalid Options. ', -1);
        }
        foreach ($validators as $validator) {
            if (count($validator) !== 3) {
                throw new InvalidArgumentException('Invalid Validator. ', -2);
            }
            list($key, $handler, $required) = $validator;
            $requiredString = $required === false ? 'false' : 'true';
            if (isset($options[$key])) {
                if (!function_exists($handler)) {
                    throw new InvalidArgumentException(
                        "Invalid Function: $key [handler: $handler require: $requiredString}]",
                        -3
                    );
                }
                if (call_user_func($handler, $options[$key])) {
                    continue;
                }
                goto fail;
            }
            if ($required) {
                fail:
                throw new InvalidArgumentException(
                    "Invalid Argument: $key [handler: $handler require: $requiredString]",
                    -4
                );
            }
        }
    }

    /**
     * 通配符校验
     *
     * @param string $rule 带有通配符的规则字符串
     * @param string $input 待校验字符串
     * @return bool
     */
    public static function wildcard(string $rule, string $input): bool
    {
        $regex = '/^' . str_replace('?', '.',
                str_replace('*', '.+', $rule)
            ) . '$/';
        preg_match($regex, $input, $match);
        return !empty($match);
    }
}
