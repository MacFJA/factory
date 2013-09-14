<?php
namespace watoki\factory;

use watoki\factory\providers\DefaultProvider;

class Factory {

    static $CLASS = __CLASS__;

    private $singletons = array();

    /** @var array|Provider[] */
    private $providers = array();

    function __construct() {
        $this->setSingleton(__CLASS__, $this);
        $this->setProvider(null, new DefaultProvider($this));
    }

    /**
     * Returns an instance of the given class.
     *
     * If the class was registed as singleton, the previous instance is returned regardless of the arguments.
     *
     * @param $class
     * @param array $args Constructor arguments that cannot be provided by the factory (indexed by parameter name)
     * @return mixed An instance of the given class
     * @throws \Exception If the class or an injected class cannot be constructed
     */
    public function getInstance($class, $args = array()) {
        $normalized = $this->normalizeClass($class);

        if (isset($this->singletons[$normalized])) {
            return $this->singletons[$normalized];
        }

        return $this->findMatchingProvider($class)->provide($class, $args);
    }

    /**
     * Returns the previously as singleton registered instance.
     *
     * Use this method if you expect the instance to have been created centrally.
     *
     * @param string $class
     * @return mixed The already existing instance of the given class
     * @throws \Exception
     */
    public function getSingleton($class) {
        $normalized = $this->normalizeClass($class);

        if (!isset($this->singletons[$normalized])) {
            throw new \Exception("Instance of [$class] does not exist.");
        }
        return $this->singletons[$normalized];
    }

    public function setSingleton($class, $instance) {
        return $this->singletons[$this->normalizeClass($class)] = $instance;
    }

    public function setProvider($class, Provider $provider) {
        $this->providers[$this->normalizeClass($class)] = $provider;
    }

    private function findMatchingProvider($class) {
        while ($class) {
            $normalized = $this->normalizeClass($class);
            foreach ($this->providers as $key => $provider) {
                if ($normalized == $key) {
                    return $provider;
                }
            }
            $class = get_parent_class($class);
        }
        return $this->providers[null];
    }

    private function normalizeClass($class) {
        return trim(strtolower($class), '\\');
    }
}
