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
            <a href="{{ route('transactions.create', ['ticket' => $ticket->id]) }}" class="btn btn-action btn-action-edit" title="Buat nota">
                <i class="fa fa-receipt"></i>
            </a>
        @endif
    @endcan
</div>
