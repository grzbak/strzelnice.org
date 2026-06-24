<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Lane;

class ShootingLane extends Component
{
    /**
     * Create a new component instance.
     */
    public Lane $lane;
    public bool $permissionDelete;
    public function __construct(Lane $lane, bool $permissionDelete = false)
    {
        $this->lane=$lane;
        $this->permissionDelete=$permissionDelete;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.shooting-lane');
    }
}
