<div class="d-flex align-items-center justify-content-end gap-1">
    @can('service ticket view')
        <a href="{{ route('service-tickets.show', $ticket) }}" class="btn btn-action btn-action-view" title="Detail">
            <i class="fa fa-eye"></i>
        </a>
    @endcan
    @can('service ticket edit')
        @if (! $ticket->isClosed())
            <a href="{{ route('service-tickets.edit', $ticket) }}" class="btn btn-action btn-action-edit" title="Ubah">
                <i class="fa fa-pencil-alt"></i>
            </a>
        @endif
    @endcan
    @can('transaction create')
        @if ($ticket->canCreateInvoice())
            <a href="{{ route('transactions.create', ['ticket' => $ticket->id]) }}" class="btn btn-action btn-action-edit" title="Buat Nota Bayar">
                <i class="fa fa-receipt"></i>
            </a>
        @endif
    @endcan
    @can('service ticket delete')
        @php
            $canDelete = in_array($ticket->status, ['received', 'cancelled'], true)
                || ($ticket->status === 'completed' && auth()->user()?->hasRole('Super Admin'));
        @endphp
        @if ($canDelete)
            <form method="POST" action="{{ route('service-tickets.destroy', $ticket) }}" class="d-inline"
                onsubmit="return confirm('Hapus tiket {{ $ticket->ticket_no }}? Data tiket yang dihapus tidak dapat dikembalikan.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-action btn-action-delete text-danger" title="Hapus">
                    <i class="fa fa-trash-alt"></i>
                </button>
            </form>
        @endif
    @endcan
</div>
