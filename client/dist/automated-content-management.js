async function loadContentForLLMFunction (event) {
  event.preventDefault()
  const atag = event.currentTarget
  atag.innerHTML = '⏳'
  atag.classList.add('llm-loading')
  const url = atag.href
  const parent = atag.closest('div.llm-ajax-holder')

  if (!url || !parent) return

  let postData = null
  const desc = atag.dataset.description
  if (desc) {
    const textarea = document.querySelector(`textarea[name="${desc}"]`)
    if (textarea) {
      postData = new URLSearchParams()
      postData.append('description', textarea.value)
    }
  }

  let html = ''
  try {
    const response = await fetch(url, {
      method: postData ? 'POST' : 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        ...(postData
          ? { 'Content-Type': 'application/x-www-form-urlencoded' }
          : {})
      },
      body: postData
    })
    if (!response.ok) throw new Error(`HTTP error ${response.status}`)
    html = await response.text()
  } catch (error) {
    console.error('Failed to load content:', error)
    html = '<p style="color: red;">Error loading content.</p>'
  }

  const wrapper = document.createElement('div')
  wrapper.innerHTML = html
  parent.replaceWith(wrapper)
}
