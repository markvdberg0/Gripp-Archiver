@foreach ($fields as $name => $f)
    @if ($f[1] === 'bool')
        <x-vink :name="$name" :label="$f[0]" :checked="$item->exists ? $item->{$name} : true" />
    @elseif ($f[1] === 'select')
        <x-keuze :name="$name" :label="$f[0]" :options="$f[3]" :value="$item->{$name}" :placeholder="in_array('nullable', $f[2]) ? '—' : null" />
    @elseif ($f[1] === 'decimal')
        <x-veld :name="$name" :label="$f[0]" inputmode="decimal" :value="\App\Support\Bedragen::input($item->{$name}, 0)" :required="in_array('required', $f[2])" />
    @else
        <x-veld :name="$name" :label="$f[0]" :type="$f[1] === 'number' ? 'number' : 'text'" :value="$item->{$name}" :required="in_array('required', $f[2])" />
    @endif
@endforeach
