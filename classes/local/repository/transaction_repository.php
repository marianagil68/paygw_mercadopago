<?php
declare(strict_types=1);

namespace paygw_mercadopago\local\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for Mercado Pago transactions.
 *
 * This class is the only access point to the
 * paygw_mercadopago_transactions table.
 *
 * @package paygw_mercadopago
 */
class transaction_repository {

    private const TABLE = 'paygw_mercadopago_transactions';

    private \moodle_database $db;

    /**
     * Constructor.
     *
     * @param \moodle_database|null $db Database connection.
     */
    public function __construct(?\moodle_database $db = null) {
        global $DB;

        $this->db = $db ?? $DB;
    }

    /**
     * Creates a transaction.
     *
     * @param \stdClass $transaction Transaction data.
     * @return int Created transaction ID.
     */
    public function create(\stdClass $transaction): int {
        $record = clone $transaction;
        $now = time();

        $record->internalstatus = $record->internalstatus ?? 'created';
        $record->delivered = $record->delivered ?? 0;
        $record->attempts = $record->attempts ?? 0;
        $record->timecreated = $record->timecreated ?? $now;
        $record->timemodified = $record->timemodified ?? $now;

        return (int) $this->db->insert_record(self::TABLE, $record);
    }

    /**
     * Finds a transaction by its internal ID.
     *
     * @param int $id Transaction ID.
     * @return \stdClass|null Transaction or null when it does not exist.
     */
    public function find_by_id(int $id): ?\stdClass {
        $record = $this->db->get_record(
            self::TABLE,
            ['id' => $id],
            '*',
            IGNORE_MISSING
        );

        return $record ?: null;
    }

    /**
     * Finds a transaction by its external reference.
     *
     * @param string $externalreference External reference.
     * @return \stdClass|null Transaction or null when it does not exist.
     */
    public function find_by_external_reference(string $externalreference): ?\stdClass {
        $record = $this->db->get_record(
            self::TABLE,
            ['externalreference' => $externalreference],
            '*',
            IGNORE_MISSING
        );

        return $record ?: null;
    }

    /**
     * Finds a transaction by its Mercado Pago payment ID.
     *
     * @param string $paymentid Mercado Pago payment ID.
     * @return \stdClass|null Transaction or null when it does not exist.
     */
    public function find_by_payment_id(string $paymentid): ?\stdClass {
        $record = $this->db->get_record(
            self::TABLE,
            ['paymentid' => $paymentid],
            '*',
            IGNORE_MISSING
        );

        return $record ?: null;
    }

    /**
     * Finds a transaction by its Mercado Pago preference ID.
     *
     * @param string $preferenceid Mercado Pago preference ID.
     * @return \stdClass|null Transaction or null when it does not exist.
     */
    public function find_by_preference_id(string $preferenceid): ?\stdClass {
        $record = $this->db->get_record(
            self::TABLE,
            ['preferenceid' => $preferenceid],
            '*',
            IGNORE_MISSING
        );

        return $record ?: null;
    }
    
    /**
     * Stores the Mercado Pago preference ID.
     *
     * @param int $id Transaction ID.
     * @param string $preferenceid Mercado Pago preference ID.
     */
    public function save_preference(int $id, string $preferenceid): void {
        $this->update_fields($id, [
            'preferenceid' => $preferenceid,
        ]);
    }

    /**
     * Stores the Mercado Pago payment ID.
     *
     * @param int $id Transaction ID.
     * @param string $paymentid Mercado Pago payment ID.
     */
    public function save_payment_reference(int $id, string $paymentid): void {
        $this->update_fields($id, [
            'paymentid' => $paymentid,
        ]);
    }

    /**
     * Updates the internal and external status of a transaction.
     *
     * @param int $id Transaction ID.
     * @param string $internalstatus Internal plugin status.
     * @param string|null $externalstatus Mercado Pago status.
     * @param int|null $timeapproved Approval timestamp.
     */
    public function update_status(
        int $id,
        string $internalstatus,
        ?string $externalstatus = null,
        ?int $timeapproved = null
    ): void {
        $fields = [
            'internalstatus' => $internalstatus,
        ];

        if ($externalstatus !== null) {
            $fields['externalstatus'] = $externalstatus;
        }

        if ($timeapproved !== null) {
            $fields['timeapproved'] = $timeapproved;
        }

        $this->update_fields($id, $fields);
    }

    /**
     * Increments the number of processing attempts.
     *
     * @param int $id Transaction ID.
     */
    public function increment_attempts(int $id): void {
        $sql = 'UPDATE {' . self::TABLE . '}
                   SET attempts = attempts + 1,
                       timemodified = :timemodified
                 WHERE id = :id';

        $this->db->execute($sql, [
            'id' => $id,
            'timemodified' => time(),
        ]);
    }

    /**
     * Registers an error for a transaction.
     *
     * @param int $id Transaction ID.
     * @param string $message Error message.
     */
    public function register_error(int $id, string $message): void {
        $sql = 'UPDATE {' . self::TABLE . '}
                   SET internalstatus = :internalstatus,
                       attempts = attempts + 1,
                       lasterror = :lasterror,
                       timemodified = :timemodified
                 WHERE id = :id';

        $this->db->execute($sql, [
            'id' => $id,
            'internalstatus' => 'error',
            'lasterror' => $message,
            'timemodified' => time(),
        ]);
    }

    /**
     * Marks a transaction as delivered.
     *
     * @param int $id Transaction ID.
     * @param int|null $timedelivered Delivery timestamp.
     */
    public function mark_as_delivered(int $id, ?int $timedelivered = null): void {
        $this->update_fields($id, [
            'internalstatus' => 'delivered',
            'delivered' => 1,
            'timedelivered' => $timedelivered ?? time(),
        ]);
    }

    /**
     * Updates selected fields of a transaction.
     *
     * @param int $id Transaction ID.
     * @param array $fields Fields to update.
     */
    private function update_fields(int $id, array $fields): void {
        $record = (object) array_merge(
            [
                'id' => $id,
                'timemodified' => time(),
            ],
            $fields
        );

        $this->db->update_record(self::TABLE, $record);
    }
}