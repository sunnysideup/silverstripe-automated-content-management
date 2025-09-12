<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="$BaseURL" />
    <title>$Title</title>
    <style>
        body {
            margin: 0;
            font-family: sans-serif;
            padding-bottom: 60px; /* space for footer buttons */
            max-width: 1400px;
            margin: 0 auto;
        }
        h1, h2 {
            text-align: center;
            padding-top: 2em;
        }
        .columns,
        .other-info {
            gap: 20px;
            margin: 0 auto;
        }
        .columns {
            display: flex;
        }
        .column {
            flex: 1;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .column h2 {
            margin-top: 0;
            font-size: 1.2rem;
            text-align: center;
        }
        .footer-buttons {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #f9f9f9;
            padding: 10px 20px;
            box-shadow: 0 -1px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        button {
            padding: 10px 20px;
            font-size: 1rem;
            cursor: pointer;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .good-button {
            background-color: #4CAF50; /* Green */

        }
        .warning-button {
            background-color:rgb(0, 102, 255); /* Orange */
        }
        .bad-button {
            background-color: #f44336; /* Red */
        }
        button {
            opacity: 0.8;
            transition: opacity 0.3s;
            border: none;
            border-radius: 5px;
            a {
               color: white;
               text-decoration: none;
            }
        }
        button:hover {
            opacity:1;
        }
    </style>
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
