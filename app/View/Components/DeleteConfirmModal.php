<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DeleteConfirmModal extends Component
{
    public string $title;
    public string $body;
    /**
     * Create a new component instance.
     */
    public function __construct(string $title, string $body)
    {
       $this->title = $title;
       $this->body=$body;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.delete-confirm-modal');
    }
}
