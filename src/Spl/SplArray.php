<?php

declare(strict_types=1);

namespace Hf3\Spl;

class SplArray extends \ArrayObject
{
    /**
     * 魔术属性读取
     * @param mixed $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this[$name])) {
            return $this[$name];
        } else {
            return null;
        }
    }

    /**
     * 魔术属性写入
     * @param mixed $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value): void
    {
        $this[$name] = $value;
    }

    /**
     * 转为 JSON 字符串
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 获取数组副本
     * @return array
     */
    public function getArrayCopy(): array
    {
        return (array)$this;
    }

    /**
     * 点路径设置值
     * @param string $path
     * @param mixed $value
     * @return void
     */
    public function set($path, $value): void
    {
        $path = explode('.', $path);
        $temp = $this;
        while ($key = array_shift($path)) {
            $temp = &$temp[$key];
        }
        $temp = $value;
    }

    /**
     * 点路径删除值
     * @param string $path
     * @return void
     */
    public function unset($path)
    {
        $finalKey = null;
        $path = explode('.', $path);
        $temp = $this;
        while (count($path) > 1 && $key = array_shift($path)) {
            $temp = &$temp[$key];
        }
        $finalKey = array_shift($path);
        if (isset($temp[$finalKey])) {
            unset($temp[$finalKey]);
        }
    }

    /**
     * 点路径获取值
     * @param mixed $key
     * @param mixed $default
     * @param mixed $target
     * @return mixed
     */
    public function get($key = null, $default = null, $target = null)
    {
        if ($target == null) {
            $target = $this->getArrayCopy();
        }
        if (is_null($key)) {
            return $target;
        }
        $key = is_array($key) ? $key : explode('.', is_int($key) ? (string)$key : $key);
        while (!is_null($segment = array_shift($key))) {
            if ((is_array($target) || $target instanceof \Traversable) && isset($target[$segment])) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                if ($segment === '*') {
                    $data = [];
                    foreach ($target as $item) {
                        $data[] = SplArray::get($key, $default, $item);
                    }
                    return $data;
                } else {
                    return $default;
                }
            }
        }
        return $target;
    }

    /**
     * 删除键
     * @param string $key
     * @return void
     */
    public function delete($key): void
    {
        $this->unset($key);
    }

    /**
     * 去重
     * @return SplArray
     */
    public function unique(): SplArray
    {
        $arrayCopy = $this->getArrayCopy();
        return new SplArray(array_unique($arrayCopy, SORT_REGULAR));
    }

    /**
     * 获取重复元素
     * @return SplArray
     */
    public function multiple(): SplArray
    {
        $arrayCopy = $this->getArrayCopy();
        $unique_arr = array_unique($arrayCopy, SORT_REGULAR);
        return new SplArray(array_udiff_uassoc($arrayCopy, $unique_arr, function ($key1, $key2) {
            if ($key1 === $key2) {
                return 0;
            }
            return 1;
        }, function ($value1, $value2) {
            if ($value1 === $value2) {
                return 0;
            }
            return 1;
        }));
    }

    /**
     * 值排序
     * @param int $flags
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function asort($flags = SORT_REGULAR): bool
    {
        return parent::asort($flags);
    }

    /**
     * 键排序
     * @param int $flags
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function ksort($flags = SORT_REGULAR): bool
    {
        return parent::ksort($flags);
    }

    /**
     * 排序
     * @param int $sort_flags
     * @return SplArray
     */
    public function sort($sort_flags = SORT_REGULAR): SplArray
    {
        $temp = $this->getArrayCopy();
        sort($temp, $sort_flags);
        return new SplArray($temp);
    }

    /**
     * 获取列
     * @param mixed $column
     * @param mixed $index_key
     * @return SplArray
     */
    public function column($column, $index_key = null): SplArray
    {
        $arrayCopy = $this->getArrayCopy();
        return new SplArray(array_column($arrayCopy, $column, $index_key));
    }

    /**
     * 键值互换
     * @return SplArray
     */
    public function flip(): SplArray
    {
        $arrayCopy = $this->getArrayCopy();
        return new SplArray(array_flip($arrayCopy));
    }

    /**
     * 过滤键
     * @param array|string $keys
     * @param bool $exclude
     * @return SplArray
     */
    public function filter($keys, $exclude = false): SplArray
    {
        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }
        $new = [];
        foreach ($this->getArrayCopy() as $name => $value) {
            if (!$exclude) {
                in_array($name, $keys) ? $new[$name] = $value : null;
            } else {
                in_array($name, $keys) ? null : $new[$name] = $value;
            }
        }
        return new SplArray($new);
    }

    /**
     * 获取键名
     * @param string|null $path
     * @return array
     */
    public function keys($path = null): array
    {
        if (!empty($path)) {
            $temp = $this->get($path);
            if (is_array($temp)) {
                return array_keys($temp);
            } else {
                return [];
            }
        }
        return array_keys((array)$this);
    }

    /**
     * 获取值
     * @return SplArray
     */
    public function values(): SplArray
    {
        $arrayCopy = $this->getArrayCopy();
        return new SplArray(array_values($arrayCopy));
    }

    /**
     * 清空数组
     * @return SplArray
     */
    public function flush(): SplArray
    {
        foreach ($this->getArrayCopy() as $key => $item) {
            unset($this[$key]);
        }
        return $this;
    }

    /**
     * 加载数组
     * @param array $data
     * @return $this
     */
    public function loadArray(array $data)
    {
        parent::__construct($data);
        return $this;
    }

    /**
     * 合并数组
     * @param array $data
     * @return SplArray
     */
    public function merge(array $data)
    {
        return $this->loadArray($data + $this->getArrayCopy());
    }

    /**
     * 转为 XML
     * @param bool $CD_DATA
     * @param string $rootName
     * @param string $subArrayItemKey
     * @return string
     */
    public function toXML($CD_DATA = false, $rootName = 'xml', $subArrayItemKey = 'item')
    {
        $data = $this->getArrayCopy();
        if ($CD_DATA) {
            $xml = new class ("<{$rootName}></{$rootName}>") extends \SimpleXMLElement {
                public function addCData($cdata_text)
                {
                    $dom = dom_import_simplexml($this);
                    $cdata = $dom->ownerDocument->createCDATASection((string)$cdata_text);
                    $dom->appendChild($cdata);
                }
            };
        } else {
            $xml = new \SimpleXMLElement("<{$rootName} ></{$rootName}>");
        }
        $parser = function ($xml, $data) use (&$parser, $CD_DATA, $subArrayItemKey) {
            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    if (!is_numeric($k)) {
                        $ch = $xml->addChild($k);
                    } else {
                        $ch = $xml->addChild($subArrayItemKey);
                    }
                    $parser($ch, $v);
                } else {
                    if (is_numeric($k)) {
                        $xml->addChild($k, $v);
                    } else {
                        if ($CD_DATA) {
                            $n = $xml->addChild($k);
                            $n->addCData($v);
                        } else {
                            $xml->addChild($k, $v);
                        }
                    }
                }
            }
        };
        $parser($xml, $data);
        unset($parser);
        $str = $xml->asXML();
        return substr($str, strpos($str, "\n") + 1);
    }
}
