<?php

/**
 * Modules core can't run without: a domain moved out of core into a module that core still requires (insurance:
 * claims, eligibility, FHIR Coverage, C-CDA, eCQM and the policy screens all ask its contract).
 *
 * The module system loads a custom module only when its row in the modules table is enabled, and only in requests
 * that run interface/globals.php; a required domain must answer in every entry point (pages, the API, the command
 * line, tests) and can't be disabled. So a required module is found here, by folder, and its container definitions
 * (config/services.php, in the format of core's config/ files) are read the first time a contract it defines is
 * asked for. Core names the folder, never a class of the module's (AGENTS.md, "Module/Core Boundaries"). How a
 * required module is declared is a maintainers' decision; this is one way, open to change.
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
use LogicException;

final class RequiredModules
{
    /**
     * The required modules, by folder under interface/modules/custom_modules. Each one's config/services.php
     * registers the module's namespace and returns a factory per contract it implements.
     */
    public const FOLDERS = ['oe-module-insurance'];

    /** @var array<string, Closure>|null */
    private static ?array $definitions = null;

    private function __construct()
    {
    }

    /**
     * A new instance of the required modules' implementation of a contract.
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
            throw new LogicException(sprintf('No required module defines %s', $contract));
        }
        $instance = $factory();
        if (!$instance instanceof $contract) {
            throw new LogicException(sprintf('A required module defines %s as %s', $contract, get_debug_type($instance)));
        }
        return $instance;
    }

    /**
     * @return array<string, Closure>
     */
    private static function load(): array
    {
        $definitions = [];
        foreach (self::FOLDERS as $folder) {
            $file = dirname(__DIR__, 2) . '/interface/modules/custom_modules/' . $folder . '/config/services.php';
            if (!is_file($file)) {
                throw new LogicException(sprintf('The required module %s is missing (%s)', $folder, $file));
            }
            $defined = (static fn(string $path): mixed => require $path)($file);
            if (!is_array($defined)) {
                throw new LogicException(sprintf('The required module %s defines no services', $folder));
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
