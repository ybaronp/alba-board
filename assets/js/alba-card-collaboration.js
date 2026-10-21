/** Shared card-member controls and comment mention suggestions. */
document.addEventListener('DOMContentLoaded', () => {
  const config = window.albaCollaboration;
  const modal = document.getElementById('alba-card-modal') || document.getElementById('alba-card-modal-admin');
  if (!config || !modal) return;
  async function api(id, path, body) {
    const response = await window.albaBoardRequest.fetch(config.url + id + '/' + path, {
      method: body ? 'POST' : 'GET', headers: {'X-WP-Nonce': config.nonce, ...(body ? {'Content-Type':'application/json'} : {})},
      ...(body ? {body:JSON.stringify(body)} : {})
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || config.error);
    return data;
  }
  function cardId() {
    return modal.querySelector('.alba-card-collaboration')?.dataset.cardId;
  }
  function memberIds(group) {
    return Array.from(group.querySelectorAll('.alba-card-member')).map(chip=>Number(chip.dataset.memberId));
  }
  function addMember(group, person) {
    if (memberIds(group).includes(Number(person.id))) return;
    const chip=document.createElement('span');chip.className='alba-card-member';chip.dataset.memberId=person.id;
    const name=document.createElement('span');name.textContent=person.name;
    const remove=document.createElement('button');remove.type='button';remove.className='alba-remove-member';
    remove.textContent='×';remove.setAttribute('aria-label',person.name+' ×');
    const hidden=document.createElement('input');hidden.type='hidden';hidden.name='card_members[]';hidden.value=person.id;
    chip.append(name,hidden,remove);group.querySelector('.alba-card-member-list').appendChild(chip);
  }
  modal.addEventListener('click', async event=> {
    const group=event.target.closest('.alba-card-collaboration');
    if (!group) return;
    if (event.target.closest('.alba-remove-member')) {event.target.closest('.alba-card-member').remove();return;}
    const save=event.target.closest('.alba-save-members');
    if (!save || save.disabled) return;
    save.disabled=true;
    const status=group.querySelector('.alba-member-status');status.textContent='';
    try {await api(group.dataset.cardId,'members',{members:memberIds(group)});status.textContent=config.saved;}
    catch(error){status.textContent=error.message;}
    finally {save.disabled=false;}
  });
  const timers=new WeakMap(), versions=new WeakMap();
  let popup=null, activeInput=null, nextListId=0;
  const suggestions=new WeakMap();
  function closeSuggestions(input) {
    if(!input)return;
    const state=suggestions.get(input);
    state?.list.replaceChildren();
    suggestions.delete(input);
    input.setAttribute('aria-expanded','false');
    input.removeAttribute('aria-activedescendant');
    input.removeAttribute('aria-controls');
  }
  function hideMentions() { closeSuggestions(activeInput); popup?.remove(); popup=null; activeInput=null; }
  function positionMentions() {
    if(!popup || !activeInput?.isConnected || !modal.getClientRects().length){hideMentions();return;}
    const rect=activeInput.getBoundingClientRect();
    const gap=6, padding=8;
    const below=Math.max(0,window.innerHeight-rect.bottom-padding);
    const above=Math.max(0,rect.top-padding);
    popup.style.width=Math.max(0,Math.min(rect.width,window.innerWidth-padding*2))+'px';
    popup.style.maxHeight=Math.min(320,Math.max(above,below))+'px';
    const height=popup.getBoundingClientRect().height;
    const upwards=below<height+gap && above>below;
    popup.style.left=Math.max(padding,Math.min(rect.left,window.innerWidth-popup.getBoundingClientRect().width-padding))+'px';
    popup.style.top=Math.max(padding,upwards?rect.top-height-gap:rect.bottom+gap)+'px';
  }
  window.addEventListener('resize',positionMentions);
  document.addEventListener('scroll',positionMentions,true);
  document.addEventListener('pointerdown',event=>{
    if(popup && !popup.contains(event.target) && event.target!==activeInput)hideMentions();
  });
  modal.addEventListener('submit',hideMentions);
  modal.addEventListener('click',event=>{
    if(event.target.closest('#alba-add-comment-btn,#alba-card-save-btn,#alba-modal-close,#alba-modal-close-admin'))hideMentions();
  });
  modal.addEventListener('input',event=> {
    const input=event.target;
    const isMember=input.id==='alba-card-members';
    const isComment=['alba-new-comment','alba-new-comment-text'].includes(input.id);
    if (!isMember && !isComment) return;
    const id=cardId();if(!id)return;
    closeSuggestions(input);
    clearTimeout(timers.get(input));
    const version=(versions.get(input)||0)+1;versions.set(input,version);
    let search=input.value, start=0, end=0;
    if(isComment){
      const before=input.value.slice(0,input.selectionStart);
      const match=before.match(/(?:^|\s)@([^@\s{}]*)$/);
      if(!match){hideMentions();return;}
      search=match[1];end=input.selectionStart;start=end-search.length-1;
    }
    timers.set(input,setTimeout(async()=>{
      try {
        const people=await api(id,'people?search='+encodeURIComponent(search));
        if(!input.isConnected || versions.get(input)!==version)return;
        let results;
        if(isMember)results=input.parentElement.querySelector('.alba-member-results');
        else {
          hideMentions();
          results=document.createElement('div');results.className='alba-mention-results';results.setAttribute('role','group');
          results.setAttribute('aria-label','@mentions');modal.appendChild(results);popup=results;activeInput=input;
        }
        results.replaceChildren();
        people.forEach(person=>{
          if(isMember&&memberIds(input.closest('.alba-card-collaboration')).includes(Number(person.id)))return;
          const option=document.createElement('button');option.type='button';option.textContent=person.name+' (@'+person.login+')';
          option.addEventListener('click',()=>{
            if(isMember){addMember(input.closest('.alba-card-collaboration'),person);input.value='';closeSuggestions(input);input.focus();}
            else {input.setRangeText('@{'+person.login+'} ',start,end,'end');hideMentions();input.focus();}
          });
          results.appendChild(option);
        });
        const options=Array.from(results.querySelectorAll('button'));
        if(options.length){
          results.id='alba-name-suggestions-'+(++nextListId);results.setAttribute('role','listbox');
          options.forEach((option,index)=>{option.id=results.id+'-'+index;option.setAttribute('role','option');option.setAttribute('aria-selected','false');});
          suggestions.set(input,{list:results,index:-1});
          input.setAttribute('aria-controls',results.id);input.setAttribute('aria-expanded','true');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-haspopup','listbox');
          if(isMember)input.setAttribute('role','combobox');
        }else closeSuggestions(input);
        if(!isMember){if(options.length)positionMentions();else hideMentions();}
      } catch(error){
        if(isMember&&input.isConnected)input.parentElement.querySelector('.alba-member-status').textContent=error.message;
      }
    },250));
  });
  const initialize=()=>modal.querySelectorAll('#alba-new-comment,#alba-new-comment-text').forEach(input=>{
    if(input.dataset.mentionHint)return;
    input.dataset.mentionHint='1';
    input.placeholder=config.hint;
  });
  new MutationObserver(()=>{initialize();if(activeInput&&!activeInput.isConnected)hideMentions();}).observe(modal,{childList:true,subtree:true});initialize();
  modal.addEventListener('keydown',event=>{
    const input=event.target,state=suggestions.get(input);
    if(!state || !state.list.isConnected || event.isComposing || event.ctrlKey || event.metaKey || event.altKey)return;
    const options=Array.from(state.list.querySelectorAll('button'));
    if(!options.length)return;
    if(event.key==='ArrowDown'||event.key==='ArrowUp'){
      event.preventDefault();event.stopImmediatePropagation();
      state.index=state.index<0?(event.key==='ArrowDown'?0:options.length-1):(state.index+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;
      options.forEach((option,index)=>{const active=index===state.index;option.classList.toggle('alba-suggestion-active',active);option.setAttribute('aria-selected',String(active));});
      input.setAttribute('aria-activedescendant',options[state.index].id);
      // Scroll only the suggestion list, never the card or page.
      const option=options[state.index].getBoundingClientRect(),list=state.list.getBoundingClientRect();
      if(option.top<list.top)state.list.scrollTop+=option.top-list.top;
      else if(option.bottom>list.bottom)state.list.scrollTop+=option.bottom-list.bottom;
    }else if(event.key==='Enter'&&state.index>=0){
      event.preventDefault();event.stopImmediatePropagation();if(!event.repeat)options[state.index].click();
    }else if(event.key==='Escape'){
      event.preventDefault();event.stopImmediatePropagation();if(input===activeInput)hideMentions();else closeSuggestions(input);
    }else if(event.key==='Tab'){if(input===activeInput)hideMentions();else closeSuggestions(input);}
  },true);
});
