<span class="pkpPublication__relation" v-if="workingPublication.relationStatus != {$smarty.const.PUBLICATION_RELATION_PUBLISHED} && workingPublication.status === getConstant('STATUS_PUBLISHED')"> 
    <dropdown
        class="pkpWorkflow__relation"
        label="{translate key="publication.relation"}"
    >
        <pkp-form class="pkpWorkflow__relateForm" v-bind="components.{$smarty.const.FORM_ID_RELATION}" @set="set">
    </dropdown>
</span>