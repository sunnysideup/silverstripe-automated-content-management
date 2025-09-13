<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="$BaseURL" />
    <title>$Title</title>
    <% include Sunnysideup/AutomatedContentManagement/Control/Includes/Styles %>
</head>
<body>

    <h1>LLM (AI) Suggestion</h1>
    <h3>Processing Record: <a href="$CMSEditLink">$Instruction.Title</a></h3>
    <h3>Based on: <a href="$Instruction.CMSEditLink">$Instruction.Title</a></h3>
    <h3>Record to be updated:
        <% if $RecordLinkEdit %><a href="$RecordLinkEdit" title="edit">✎</a><% end_if %>
        <% if $RecordLinkView %><a href="$RecordLinkView" title="view">👁 $RecordTitle</a><% else %>$RecordTitle<% end_if %>
    </h3>
    <h3>Field to be updated: <i>$Instruction.FieldToChangeNice</i></h3>

    <% if $IsHTML %>
    <h2>
    Formatted View
    <br />Before ➔ After
    </h2>
    <div class="columns">
        <div class="column">
            <div class="value">$BeforeHTMLValue</div>
        </div>
        <div class="column">
            <div class="value">$AfterHTMLValue</div>
        </div>
    </div>
    <% end_if %>
    <h2>Before ➔ After</h2>
    <div class="columns">
        <div class="column">
            <div class="value">$BeforeHumanValue</div>
        </div>
        <div class="column">
            <div class="value">$AfterHumanValue</div>
        </div>
    </div>



    <h2>Other information</h2>
    <div class="columns">
        <div class="column">
            <h3>Instructions Provided</h3>
            <pre>$HydratedInstructions</pre>
        </div>
        <div class="column">
            <h3>When was it lodged?</h3>
            <p>About {$Created.Ago}</p>
            <h3>Status</h3>
            <p>$Status</p>
        </div>
    </div>

    <div class="footer-buttons">
    <% if $CanBeReviewed %>
        <button class="button good-button"><a href="$AcceptLink">Accept</a></button>
        <button class="button warning-button"><a href="$AcceptAndUpdateLink">Accept and Update Record</a></button>
        <button class="button bad-button"><a href="$RejectLink">Reject Suggestion</a></button>
    <% else %>
    <p>You can not change the status of this suggestion.</p>
    <% end_if %>
    </div>
<script>
document.querySelectorAll('button').forEach(button => {
  button.addEventListener('click', e => {
    const confirmed = confirm('Are you sure? Your selection is not reversible.');
    if (!confirmed) {
      e.preventDefault();
    }
  });
});
</script>
</body>
</html>
