<?php

declare(strict_types=1);

// Global UserException — matches the modern BGA framework's
// Bga\GameFramework\UserException. Game code that runs in both Studio and
// the test harness can simply `throw new UserException('code')` without
// importing a namespace.
//
// Bracketed namespace syntax is required here: a single file can only mix a
// global-namespace declaration with a namespaced one when BOTH use braces.
namespace {
    if (!class_exists('UserException')) {
        class UserException extends \Exception {}
    }
}

namespace BgaHarness {
    class BgaUserException extends \Exception {}

    class BgaVisibleSystemException extends \Exception {}

    class BgaSystemException extends \Exception {}
}
