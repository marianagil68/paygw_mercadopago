<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace paygw_mercadopago\local\exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Excepción lanzada cuando una notificación Webhook no supera las validaciones.
 */
class invalid_webhook_exception extends \runtime_exception {
}