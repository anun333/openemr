<?php

/**
 * Packages core can't run without: a domain moved out of core into a Composer package that core still requires
 * (insurance: claims, eligibility, FHIR Coverage, C-CDA, eCQM and the policy screens all ask its contract).
 *
 * Core's composer.json requires each one, so Composer installs and autoloads it like any dependency. A required
 * package isn't a custom module: the module system loads a custom module only when its row in the modules table is
 * enabled, and only in requests that run interface/globals.php, while a required domain must answer in every entry
 * point (pages, the API, the command line, tests) and can't be disabled. So core finds each required package through
 * Composer (InstalledVersions) and reads its container definitions (config/services.php, in the format of core's
 * config/ files) the first time a contract it defines is asked for. Core names the package, as composer.json does,
 * never a class of it (AGENTS.md, "Module/Core Boundaries"). How a required package is declared is a maintainers'
 * decision; this is one way, open to change.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Core;

use Closure;
use Composer\InstalledVersions;
use LogicException;

final class RequiredPackages
{
    /**
     * The required packages, as core's composer.json requires them. Each one's config/services.php returns a factory
     * per contract it implements.
     */
    public const PACKAGES = ['openemr/oe-module-insurance'];

    /** @var array<string, Closure>|null */
    private static ?array $definitions = null;

    private function __construct()
    {
    }

    /**
     * A new instance of the required packages' implementation of a contract.
     *
     * @template T of object
     * @param class-string<T> $contract
     * @return T
     */
    public static function make(string $contract): object
    {
        self::$definitions ??= self::load();
        $factory = self::$definitions[$contract] ?? null;
        if ($factory === null) {
            throw new LogicException(sprintf('No required package defines %s', $contract));
        }
        $instance = $factory();
        if (!$instance instanceof $contract) {
            throw new LogicException(sprintf('A required package defines %s as %s', $contract, get_debug_type($instance)));
        }
        return $instance;
    }

    /**
     * @return array<string, Closure>
     */
    private static function load(): array
    {
        $definitions = [];
        foreach (self::PACKAGES as $package) {
            $root = InstalledVersions::isInstalled($package) ? InstalledVersions::getInstallPath($package) : null;
            if ($root === null) {
                throw new LogicException(sprintf('The required package %s is not installed (run composer install)', $package));
            }
            $file = $root . '/config/services.php';
            if (!is_file($file)) {
                throw new LogicException(sprintf('The required package %s has no config/services.php (%s)', $package, $file));
            }
            $defined = (static fn(string $path): mixed => require $path)($file);
            if (!is_array($defined)) {
                throw new LogicException(sprintf('The required package %s defines no services', $package));
            }
            foreach ($defined as $id => $factory) {
                if (is_string($id) && $factory instanceof Closure) {
                    $definitions[$id] = $factory;
                }
            }
        }
        return $definitions;
    }
}
