{TinyMCE()}
<div class="card">
    <header class="card-header">
        Edit Sidebox <a class="relative font-sans font-normal text-sm inline-flex items-center justify-center leading-5 no-underline h-8 px-3 py-2 space-x-1 border nui-focus transition-all duration-300 disabled:opacity-60 disabled:cursor-not-allowed hover:enabled:shadow-none text-muted-700 border-muted-300 dark:text-white dark:bg-muted-700 dark:border-muted-600 dark:hover:enabled:bg-muted-600 hover:enabled:bg-muted-50 dark:active:enabled:bg-muted-700/70 active:enabled:bg-muted-100 rounded-md pull-right" href="{$url}admin/sidebox">Back</a>
    </header>
    <div class="card-body">
        <form role="form" onSubmit="Sidebox.save(this, {$sidebox.id}); return false" id="submit_form">
            <div class="form-group row">
                <label class="col-sm-2 col-form-label" for="displayName">Headline</label>
                <div class="col-sm-10">
                    <input class="form-control nui-focus border-muted-300 text-muted-600 placeholder:text-muted-300 dark:border-muted-700 dark:bg-muted-900/75 dark:text-muted-200 dark:placeholder:text-muted-500 dark:focus:border-muted-700 peer w-full border bg-white font-monospace transition-all duration-300 disabled:cursor-not-allowed disabled:opacity-75 px-2 h-10 py-2 text-sm leading-5 px-3 rounded" type="text" name="displayName" id="displayName" value="{htmlspecialchars($sidebox.displayName)}"/>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-sm-2 col-form-label" for="type">Sidebox module</label>
                <div class="col-sm-10">
                    <select class="form-control nui-focus border-muted-300 text-muted-600 placeholder:text-muted-300 focus:border-muted-300 focus:shadow-muted-300/50 dark:border-muted-700 dark:bg-muted-900/75 dark:text-muted-200 dark:placeholder:text-muted-600 dark:focus:border-muted-700 dark:focus:shadow-muted-800/50 peer w-full cursor-pointer appearance-none border bg-white font-sans focus:shadow-lg px-2 pe-9 h-10 py-2 text-sm leading-5 px-3 pe-6 rounded px-3" id="type" name="type" onChange="Sidebox.toggleCustom(this)">
                        {foreach from=$sideboxModules item=module key=name}
                        <option value="{$name}" {if $sidebox.type == preg_replace("/sidebox_/", "", $name)}selected{/if}>{$module.name}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-sm-2 col-form-label" for="visibility">Visibility mode</label>
                <div class="col-sm-10">
                    <select class="form-control nui-focus border-muted-300 text-muted-600 placeholder:text-muted-300 focus:border-muted-300 focus:shadow-muted-300/50 dark:border-muted-700 dark:bg-muted-900/75 dark:text-muted-200 dark:placeholder:text-muted-600 dark:focus:border-muted-700 dark:focus:shadow-muted-800/50 peer w-full cursor-pointer appearance-none border bg-white font-sans focus:shadow-lg px-2 pe-9 h-10 py-2 text-sm leading-5 px-3 pe-6 rounded px-3" name="visibility" id="visibility" onChange="if(this.value == 'group'){ $('#groups').fadeIn(300); } else { $('#groups').fadeOut(300); }">
                        <option value="everyone" {if !$sidebox.permission}selected{/if}>Visible to everyone</option>
                        <option value="group" {if $sidebox.permission}selected{/if}>Controlled per group</option>
                    </select>
                </div>
                <div {if !$sidebox.permission}style="display:none"{/if} id="groups">
                    Please manage the group visibility via <a href="{$url}admin/aclmanager/groups">the group manager</a> once you have created the sidebox
                </div>
            </div>
            <div class="form-group row mb-3">
                <label class="col-sm-2 col-form-label" for="location">Location</label>
                <div class="col-sm-10">
                    <select class="form-control nui-focus border-muted-300 text-muted-600 placeholder:text-muted-300 focus:border-muted-300 focus:shadow-muted-300/50 dark:border-muted-700 dark:bg-muted-900/75 dark:text-muted-200 dark:placeholder:text-muted-600 dark:focus:border-muted-700 dark:focus:shadow-muted-800/50 peer w-full cursor-pointer appearance-none border bg-white font-sans focus:shadow-lg px-2 pe-9 h-10 py-2 text-sm leading-5 px-3 pe-6 rounded px-3" name="location" id="location">
                        <option value="side" {if $sidebox.location == 'side'}selected{/if}>Side</option>
                        <option value="top" {if $sidebox.location == 'top'}selected{/if}>Top (Before the news)</option>
                        <option value="bottom" {if $sidebox.location == 'bottom'}selected{/if}>Bottom (After the news)</option>
                    </select>
                </div>
            </div>

            {* Pages.Start *}
            {if isset($pages) && $pages|is_array && count($pages)}
                <div class="form-group row mb-3">
                    <label class="col-sm-2 col-form-label">Pages</label>
                    <div class="col-sm-10">
                        <div class="row row-cols-2 row-cols-md-6">
                            {foreach from=$pages item=item}
                                <div class="col">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pages[]" id="page_{$item}" value="{$item}" {if $sidebox.pages|is_array && in_array($item, $sidebox.pages)}checked{/if} {if $sidebox.pages|is_array && in_array('*', $sidebox.pages)}disabled{/if} />
                                        <label class="form-check-label" for="page_{$item}">{$item}</label>
                                    </div>
                                </div>
                            {/foreach}
                        </div>

                        <hr class="my-3" />

                        <div class="row row-cols-2 row-cols-md-6 justify-content-md-end">
                            <div class="col">
                                <a href="javascript:void(0)" onclick="[...document.querySelectorAll('input[name=\'pages[]\']')].map(el => { if(el.value === '*') { return; } el.checked = true; })"><i class="fa-duotone fa-toggle-on align-top"></i> Check all</a>
                            </div>

                            <div class="col">
                                <a href="javascript:void(0)" onclick="[...document.querySelectorAll('input[name=\'pages[]\']')].map(el => { if(el.value === '*') { return; } el.checked = false; })"><i class="fa-duotone fa-toggle-off align-top"></i> Uncheck all</a>
                            </div>

                            <div class="col">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pages[]" id="page_*" value="*" onclick="[...document.querySelectorAll('input[name=\'pages[]\']')].map(el => { if(el.value === '*') { return; } if(this.checked) { el.disabled = true; } else { el.disabled = false; } })" {if $sidebox.pages|is_array && in_array('*', $sidebox.pages)}checked{/if} />
                                    <label class="form-check-label" for="page_*">Visible in all pages</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
            {* Pages.End *}
        </form>
        <div id="custom_field" style="padding-top:0px;padding-bottom:0px;{if $sidebox.type != "custom"}display:none{/if}" >
        <textarea name="content" class="form-control tinymce mb-3" id="content" cols="30" rows="10">{$sideboxCustomText}</textarea>
    </div>
    <form class="mt-3" role="form" onSubmit="Sidebox.save(document.getElementById('submit_form'), {$sidebox.id}); return false">
        <button type="submit" class="relative font-sans font-normal text-sm inline-flex items-center justify-center leading-5 no-underline h-8 px-3 py-2 space-x-1 border nui-focus transition-all duration-300 disabled:opacity-60 disabled:cursor-not-allowed hover:enabled:shadow-none text-muted-700 border-muted-300 dark:text-white dark:bg-muted-700 dark:border-muted-600 dark:hover:enabled:bg-muted-600 hover:enabled:bg-muted-50 dark:active:enabled:bg-muted-700/70 active:enabled:bg-muted-100 rounded-md">Save sidebox</button>
    </form>
</div>
</div>
<script>
    require([Config.URL + "application/themes/admin/assets/js/mli.js"], function()
    {
    	new MultiLanguageInput($("#displayName"));
    });
</script>