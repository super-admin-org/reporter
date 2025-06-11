<?php

namespace SuperAdmin\Admin\Reporter\Actions;

use SuperAdmin\Admin\Actions\RowAction;

class ViewReport extends RowAction
{
    public $name = 'View Report';
    public $icon = 'search-minus';

    public function render()
    {
        $path = $this->getResource().'/'.$this->getKey();

        return "<a href=\"$path\"><i class=\"icon-search-plus\"></i></a>";
    }
}
