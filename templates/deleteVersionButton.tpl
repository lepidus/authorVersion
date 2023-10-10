<pkp-button
    v-if="workingPublication.id === latestPublicationId && workingPublication.status != getConstant('STATUS_PUBLISHED') && workingPublication.version > 1"
    ref="deleteVersionButton"
    :is-warnable="true"
    @click="$modal.show('deleteVersion')"
>
    {translate key="plugins.generic.authorVersion.deleteVersion"}
</pkp-button>
<modal
    v-bind="MODAL_PROPS"
    name="deleteVersion"
    @closed="setFocusToRef('deleteVersionButton')"
>
    <modal-content
        id="deleteVersionModal"
        modal-name="deleteVersion"
        title="{translate key="plugins.generic.authorVersion.deleteVersion"}"
    >
        <pkp-form v-bind="components.deleteVersionForm" @set="set" @success="location.reload()"></pkp-form>
    </modal-content>
</modal>