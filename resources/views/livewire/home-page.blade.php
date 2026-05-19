<div>
     <flux:heading size="xl">Home</flux:heading>
     <flux:text class="mt-2">Amazing.</flux:text>
     <p>{{ auth()->check() ? "You are logged in" : "You are not logged in" }}</p
</div>
