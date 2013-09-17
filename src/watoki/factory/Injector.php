<?php
namespace watoki\factory;

class Injector {

    const INJECTION_MARKER = '<-';

    /**
     * @var Factory
     */
    private $factory;

    function __construct(Factory $factory) {
        $this->factory = $factory;
    }

    public function injectConstructor($class, $args) {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->getConstructor()) {
            return $reflection->newInstance();
        }

        try {
            return $reflection->newInstanceArgs($this->injectMethodArguments($reflection->getConstructor(), $args));
        } catch (\Exception $e) {
            throw new \Exception('Error while injecting constructor of ' . $reflection->getName() . ': ' . $e->getMessage());
        }
    }

    public function injectMethodArguments(\ReflectionMethod $method, array $args) {
        $argArray = array();
        foreach ($method->getParameters() as $i => $param) {
            if (array_key_exists($param->getName(), $args)) {
                $arg = $args[$param->getName()];
            } else if (array_key_exists($i, $args)) {
                $arg = $args[$i];
            } else if ($param->isDefaultValueAvailable()) {
                $arg = $param->getDefaultValue();
            } else if ($param->getClass()) {
                $arg = $this->factory->getInstance($param->getClass()->getName());
            } else {
                throw new \Exception("Cannot inject parameter [{$param->getName()}]. No class or value given in "
                        . json_encode(array_keys($args)));
            }
            $argArray[$param->getName()] = $arg;
        }
        return $argArray;
    }

    /**
     * @param object $object The object that the properties are injected into
     * @param callable $filter Function to determine if the passed property annotation should be included
     * @throws \Exception
     */
    public function injectPropertyAnnotations($object, $filter) {
        $classReflection = new \ReflectionClass($object);
        $resolver = new ClassResolver($classReflection);

        $matches = array();
        preg_match_all('/@property\s+(\S+)\s+\$?(\S+).*/', $classReflection->getDocComment(), $matches);

        foreach ($matches[0] as $i => $match) {
            if (!$filter(trim($match))) {
                continue;
            }

            $this->injectProperty($matches[2][$i], $object, $resolver, $matches[1][$i], $classReflection);
        }
    }

    public function injectProperties($object, $filter) {
        $classReflection = new \ReflectionClass($object);
        $resolver = new ClassResolver($classReflection);

        foreach ($classReflection->getProperties() as $property) {
            $matches = array();
            preg_match('/@var\s+(\S+).*/', $property->getDocComment(), $matches);

            if (empty($matches) || !$filter($property->getDocComment())) {
                continue;
            }

            $this->injectProperty($property->getName(), $object, $resolver, $matches[1], $classReflection);
        }
    }

    private function injectProperty($property, $object, ClassResolver $resolver, $className, \ReflectionClass $classReflection) {
        $class = $resolver->resolve($className);

        if (!$class) {
            throw new \Exception("Error while loading dependency [$property] of [{$classReflection->getShortName()}]: Could not find class [$className].");
        }

        if ($classReflection->hasProperty($property)) {
            $reflectionProperty = $classReflection->getProperty($property);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($object, $this->factory->getInstance($class));
        } else {
            $object->$property = $this->factory->getInstance($class);
        }
    }
}