@props(['active'])
<span class="badge {{ $active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $active ? 'Activo' : 'Inactivo' }}</span>
