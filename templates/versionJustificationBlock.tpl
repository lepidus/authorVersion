<div class="item versionJustification">
	<h2 class="label">
        {translate key="plugins.generic.authorVersion.versionJustification"}
    </h2>
	<div class="value">
        {$versionJustification}
    </div>
</div>

<script>
    const categoriesBlock = document.getElementsByClassName("item categories")[0];
    const justificationBlock = document.getElementsByClassName('item versionJustification')[0];
    categoriesBlock.parentNode.insertBefore(justificationBlock, categoriesBlock);
</script>