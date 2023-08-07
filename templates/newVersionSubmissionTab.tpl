{if array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], (array)$userRoles)}
    <tab id="newVersion" label="{translate key="plugins.generic.authorVersion.newVersions"}" :badge="0">
        <submissions-list-panel
        ></submissions-list-panel>
    </tab>
{/if}