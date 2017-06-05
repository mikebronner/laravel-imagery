var expirationDate = new Date();
//TODO: is it reasonable to reload the page on first access? Thinking not, as it would impact user experience negatively.
expirationDate.setTime(expirationDate.getTime() + (365*24*60*60*1000));
document.cookie = 'screenWidth=' + screen.width + '; expires=' + expirationDate.toUTCString() + '; path=/';
document.cookie = 'screenHeight=' + screen.height + '; expires=' + expirationDate.toUTCString() + '; path=/';
