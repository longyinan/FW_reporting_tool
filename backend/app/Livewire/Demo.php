<?php

namespace App\Livewire;

use Livewire\Component;

class Demo extends Component
{
    public int $count = 0;

    public function mount(int $initialCount = 0): void
    {
        $this->count = $initialCount;
    }

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }

    public function render()
    {
        return view('livewire.demo');
    }
}
