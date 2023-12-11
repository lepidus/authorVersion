{if array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], (array) $userRoles)}
    <tab id="newVersion" label="{translate key="plugins.generic.authorVersion.newVersions"}" :badge="components.newVersion.itemsMax">
        <submissions-list-panel
            v-bind="components.newVersion"
            @set="set"
        ></submissions-list-panel>
    </tab>
{/if}