<span 
    class="pkpPublication__versionJustification"
    v-if="workingPublication.version > 1 && workingPublication.versionJustification"
    style="margin: 0 0.5rem 0 0"
> 
    <dropdown
        class="pkpWorkflow__versionJustification"
        label="{translate key="plugins.generic.authorVersion.versionJustification"}"
        :is-link="true"
    >
        <p>"{{ workingPublication.versionJustification }}"</p>
    </dropdown>
</span>