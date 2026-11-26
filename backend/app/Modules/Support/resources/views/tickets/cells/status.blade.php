<x-ui::badge :tone="$ticket->status->tone()">{{ $surface === 'admin' ? $ticket->status->label() : $ticket->status->requesterLabel() }}</x-ui::badge>
