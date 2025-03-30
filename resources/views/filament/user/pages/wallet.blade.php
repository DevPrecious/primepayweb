<x-filament-panels::page>
    <x-filament::card>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h3 class="text-lg font-semibold mb-2">Available Balance</h3>
                <p class="text-2xl font-bold">

                    @if(auth()->user()->wallet)
                        {{ auth()->user()->wallet->balance }}
                    @else
                        0.00
                    @endif
                </p>
            </div>
        </div>
    </x-filament::card>
    <x-filament::card>
        @if(!auth()->user()->accountNumber)
        <x-filament-panels::form wire:submit="createAccount">
            <h3 class="text-lg font-semibold mb-2">Create Virtual Account</h3>
            <x-filament::button type="submit" class="">Create</x-filament::button>
        </x-filament-panels::form>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-lg font-semibold mb-2">Account Number</h3>
                    <p class="text-2xl font-bold">{{ auth()->user()->accountNumber->account_number }}</p>
                    <p class="text-md font-bold">{{ auth()->user()->accountNumber->account_name }}</p>
                </div>
            </div>
        @endif
    </x-filament::card>
</x-filament-panels::page>
