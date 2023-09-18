<span 
    class="pkpPublication__versionJustification"
    v-if="workingPublication.version > 1 && (workingPublication.versionJustification || currentPublication.versionJustification)"
> 
    <dropdown
        class="pkpWorkflow__versionJustification"
        label="{translate key="plugins.generic.authorVersion.versionJustification"}"
        :is-link="true"
    >
        <pkp-form
            class="pkpWorkflow__versionJustificationForm"
            v-bind="components.{$smarty.const.FORM_VERSION_JUSTIFICATION}"
            @set="set"
        />
    </dropdown>
</span>