<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * What one evaluator found: the items that need attention and how many checks it looked at.
 */
class AttentionEvaluation
{

    /**
     * The items that need attention.
     *
     * @var array
     */
    private array $items = [];

    /**
     * How many checks were evaluated, healthy ones included.
     *
     * @var integer
     */
    private int $checked = 0;


    /**
     * Records one healthy check.
     *
     * @return void
     */
    public function addHealthy(): void
    {
        $this->checked++;

    }//end addHealthy()


    /**
     * Records one check that needs attention.
     *
     * @param AttentionItem $item The item.
     *
     * @return void
     */
    public function addItem(AttentionItem $item): void
    {
        $this->checked++;
        $this->items[] = $item;

    }//end addItem()


    /**
     * The items that need attention.
     *
     * @return AttentionItem[]
     */
    public function getItems(): array
    {
        return $this->items;

    }//end getItems()


    /**
     * How many checks were evaluated.
     *
     * @return integer
     */
    public function getChecked(): int
    {
        return $this->checked;

    }//end getChecked()


}//end class
