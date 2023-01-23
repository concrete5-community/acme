<?php

namespace Acme\Filesystem;

use Acme\Entity\RemoteServer;
use Acme\Exception\RuntimeException;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use Punic\Comparer;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Manage the list of filesystem drivers.
 */
final class DriverManager
{
    /**
     * @var \Concrete\Core\Foundation\Environment\FunctionInspector
     */
    private $functionInspector;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    private $config;

    public function __construct(FunctionInspector $functionInspector, Repository $config)
    {
        $this->functionInspector = $functionInspector;
        $this->config = $config;
    }

    /**
     * Get the name of a driver.
     *
     * @param string $handle
     * @param string|mixed $onNotFound
     *
     * @return string|mixed
     */
    public function getDriverName($handle, $onNotFound = '')
    {
        $data = $this->getDriverDetails($handle);

        return $data === null ? $onNotFound : $data['name'];
    }

    /**
     * Get the list of drivers.
     *
     * @param bool|null $available set to TRUE to get only the available drivers, set to FALSE to get only the not available drivers, set to NULL to get all the drivers
     * @param string $requiredInterface the fully-qualified name of the interface that the drivers should implement
     *
     * @return array keys are the handles, values are arrays with keys ['name', 'available', 'loginFlags']
     */
    public function getDrivers($available = true, $requiredInterface = '')
    {
        $list = [];
        foreach (array_keys($this->config->get('acme::filesystem.drivers')) as $handle) {
            $data = $this->getDriverDetails($handle);
            if ($data === null) {
                continue;
            }
            if ($available !== null && $data['available'] !== $available) {
                continue;
            }
            if ($requiredInterface !== '' && !is_a($data['class'], $requiredInterface, true)) {
                continue;
            }
            $list[$handle] = ['name' => $data['name'], 'available' => $data['available'], 'loginFlags' => $data['loginFlags']];
        }
        $cmp = new Comparer();
        uasort($list, function (array $a, array $b) use ($cmp) {
            return $cmp->compare($a['name'], $b['name']);
        });

        return $list;
    }

    /**
     * Get the handle of the driver for the local filesystem.
     *
     * @return string
     */
    public function getLocalDriverHandle()
    {
        return $this->config->get('acme::filesystem.local_driver');
    }

    /**
     * Get the driver for the local filesystem.
     *
     * @return \Acme\Filesystem\Driver\Local
     */
    public function getLocalDriver()
    {
        $data = $this->getDriverDetails($this->getLocalDriverHandle());
        if ($data === null || is_a($data['class'], RemoteDriverInterface::class, true) || !$data['available']) {
            throw new RuntimeException(t('The driver for the local filesystem is not configured correctly'));
        }

        return call_user_func([$data['class'], 'create'], $data['handle'], $data['options']);
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Filesystem\RemoteDriverInterface
     */
    public function getRemoteDriver(RemoteServer $remoteServer)
    {
        $handle = $remoteServer->getDriverHandle();
        $data = $this->getDriverDetails($handle);
        if ($data === null) {
            throw new RuntimeException(t('The driver with handle %s configured for the remote server does not exist', $handle));
        }
        if (!is_a($data['class'], RemoteDriverInterface::class, true)) {
            throw new RuntimeException(t('The driver with handle %s configured for the remote server is not a remote driver', $handle));
        }
        if (!$data['available']) {
            throw new RuntimeException(t('The driver "%s" (handle %s) is not available', $data['name'], $handle));
        }
        $driver = call_user_func([$data['class'], 'create'], $data['handle'], $data['options']);

        return $driver->setRemoteServer($remoteServer);
    }

    /**
     * Get the details of a driver given its handle.
     *
     * @param string $handle
     *
     * @return array|null
     */
    private function getDriverDetails($handle)
    {
        $data = $this->config->get('acme::filesystem.drivers.' . $handle);
        if (!is_array($data)) {
            return null;
        }
        $class = array_get($data, 'class');
        if (!is_string($class) || $class === '' || !is_a($class, DriverInterface::class, true)) {
            return null;
        }
        unset($data['class']);

        return [
            'handle' => $handle,
            'class' => $class,
            'options' => $data,
            'name' => call_user_func([$class, 'getName'], $data),
            'available' => call_user_func([$class, 'isAvailable'], $data, $this->functionInspector),
            'name' => call_user_func([$class, 'getName'], $data),
            'loginFlags' => is_a($class, RemoteDriverInterface::class, true) ? call_user_func([$class, 'getLoginFlags'], $data) : RemoteDriverInterface::LOGINFLAG_NONE,
        ];
    }
}
