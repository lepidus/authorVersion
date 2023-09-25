<span 
    class="pkpPublication__versionJustification"
    v-if="workingPublication.versionJustification"
> 
    <dropdown
        class="pkpWorkflow__versionJustification"
        label="{translate key="plugins.generic.authorVersion.versionJustification"}"
        :is-link="true"
    >
        <pkp-form
            class="pkpWorkflow__versionJustificationForm"
            v-if="workingPublication.id == latestPublication.id && latestPublication.status != getConstant('STATUS_PUBLISHED')"
            v-bind="components.{$smarty.const.FORM_VERSION_JUSTIFICATION}"
            @set="set"
        />
        <span
            v-if="workingPublication.status === getConstant('STATUS_PUBLISHED') || workingPublication.id != latestPublication.id"
        >
            {{ workingPublication.versionJustification }}
        </span>
    </dropdown>
</span>