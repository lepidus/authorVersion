<div class="item versionJustification">
	<h2 class="label">
        {translate key="plugins.generic.authorVersion.versionJustification"}
    </h2>
	<div class="value">
        {$versionJustification}
    </div>
</div>

<script>
    function insertAfter(newNode, referenceNode) {ldelim}
        referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
    {rdelim}
    
    const publishedBlock = document.getElementsByClassName("item published")[0];
    const justificationBlock = document.getElementsByClassName('item versionJustification')[0];
    insertAfter(justificationBlock, publishedBlock);
</script>