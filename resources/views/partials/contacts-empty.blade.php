<x-nx.empty art="nc-people" class="ns-ct__empty"
    :title="$search !== '' ? 'No contacts match your search' : 'No contacts yet'"
    :text="$search !== '' ? 'Try a different name or number.' : 'Add someone or import a CSV / vCard, then call them straight from here.'">
    @if ($search === '')
        <button type="button" @click="openAdd()" class="ns-cta ns-cta--pill" style="margin:14px auto 0"><x-nx.icon name="plus" /> Add a contact</button>
    @endif
</x-nx.empty>
