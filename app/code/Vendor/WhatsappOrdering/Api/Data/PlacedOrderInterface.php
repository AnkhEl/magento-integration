<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api\Data;

/**
 * Result of a place-order request.
 *
 * @api
 */
interface PlacedOrderInterface
{
    /**
     * True when an order exists for this request (new or already placed)
     *
     * @return bool
     */
    public function getOk();

    /**
     * @param bool $ok
     * @return $this
     */
    public function setOk($ok);

    /**
     * Reasons no order was placed, written for the customer
     *
     * @return string[]
     */
    public function getProblems();

    /**
     * @param string[] $problems
     * @return $this
     */
    public function setProblems($problems);

    /**
     * Order number (increment id)
     *
     * @return string|null
     */
    public function getOrderNumber();

    /**
     * @param string|null $orderNumber
     * @return $this
     */
    public function setOrderNumber($orderNumber);

    /**
     * What the customer pays
     *
     * @return float|null
     */
    public function getGrandTotal();

    /**
     * @param float|null $grandTotal
     * @return $this
     */
    public function setGrandTotal($grandTotal);

    /**
     * Currency code
     *
     * @return string|null
     */
    public function getCurrency();

    /**
     * @param string|null $currency
     * @return $this
     */
    public function setCurrency($currency);

    /**
     * True when this idempotency key had already produced the order
     *
     * @return bool
     */
    public function getAlreadyPlaced();

    /**
     * @param bool $alreadyPlaced
     * @return $this
     */
    public function setAlreadyPlaced($alreadyPlaced);
}
