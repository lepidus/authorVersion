<template slot="actions">
    <pkp-button
        v-if="workingPublication.relationStatus != {$smarty.const.PUBLICATION_RELATION_PUBLISHED} && canCreateNewVersion"
        ref="createVersion"
        @click="createVersion"
    >
        {translate key="publication.createVersion"}
    </pkp-button>
    <pkp-button
        v-if="workingPublication.status != getConstant('STATUS_PUBLISHED') && workingPublication.version > 1 && !workingPublication.versionJustification"
        ref="submitVersionButton"
        @click="$modal.show('submitVersion')"
    >
        {translate key="plugins.generic.authorVersion.publication.submitVersion"}
    </pkp-button>
    <modal
        name="submitVersion"
        title="{translate key="plugins.generic.authorVersion.publication.submitVersion"}"
        close-label="common.close"
        @closed="setFocusToRef('submitVersionButton')"
    >
        <pkp-form v-bind="components.submitVersionForm" @set="set" @success="location.reload()"></pkp-form>
    </modal>
</template>